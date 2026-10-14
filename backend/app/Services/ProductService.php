<?php

namespace App\Services;

use App\Enums\AuctionStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Enums\UserStatus;
use App\Models\Auction;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductService
{
    public function __construct(private ProductLocks $locks, private SlugGenerator $slugs, private AuditService $audit, private FixedPriceEligibility $claims) {}

    public function create(User $user, array $data): Product
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            try {
                return DB::transaction(function () use ($user, $data): Product {
                    $actor = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                    $shop = $actor->shop()->lockForUpdate()->first();
                    Gate::forUser($actor)->authorize('create', Product::class);
                    Category::orderBy('id')->lockForUpdate()->get();
                    $this->locks->validateCategory($data['category_id']);
                    $data = $this->normalizeCompareAt($data);
                    $this->assertCompareAt($data['price'], $data['compare_at_price'] ?? null);
                    $product = $shop->products()->create([...$data, 'price' => bcadd($data['price'], '0', 2), 'slug' => $this->slugs->generate(Product::withTrashed(), $data['name'], 240), 'status' => ProductStatus::DRAFT]);
                    $this->audit->record($actor, 'products', $product->id, 'product.created', null, ProductStatus::DRAFT->value, null);

                    return $product;
                }, 3);
            } catch (UniqueConstraintViolationException) {
            }
        }
        abort(409, 'Product creation conflicted. Please retry.');
    }

    public function update(User $user, Product $product, array $data): Product
    {
        return DB::transaction(function () use ($user, $product, $data): Product {
            $locked = $this->locks->lock($user, $product, 'update');
            $this->claims->assertAvailable($locked->id);
            if (array_key_exists('stock_quantity', $data)) {
                abort_unless(isset($data['expected_updated_at']) && CarbonImmutable::parse($data['expected_updated_at'])->equalTo($locked->updated_at), 409, 'Product changed. Reload before adjusting stock.');
            }
            unset($data['expected_updated_at']);
            if (isset($data['category_id'])) {
                $this->locks->validateCategory($data['category_id']);
            }
            if (isset($data['price'])) {
                $data['price'] = bcadd($data['price'], '0', 2);
            }
            $data = $this->normalizeCompareAt($data);
            $from = $locked->status;
            $previousStock = $locked->stock_quantity;
            $locked->fill($data);
            $this->assertCompareAt($locked->price, $locked->compare_at_price);
            if (! $locked->isDirty()) {
                return $locked;
            }
            $content = $locked->isDirty(['name', 'description', 'category_id', 'price', 'compare_at_price', 'condition']);
            $stock = $locked->isDirty('stock_quantity');
            if ($stock && ! $content) {
                // Listings are not reviewed before sale: selling out closes one and restocking reopens it.
                if ($from === ProductStatus::ACTIVE && $locked->stock_quantity === 0) {
                    $locked->status = ProductStatus::SOLD;
                } elseif ($from === ProductStatus::SOLD && $locked->stock_quantity > 0) {
                    $locked->status = $this->reopenStatus($locked);
                }
                $locked->save();
                $this->audit->record($user, 'products', $locked->id, 'product.stock_adjusted', $from->value, $locked->status->value, 'stock: '.$previousStock.' → '.$locked->stock_quantity);

                return $locked;
            }
            // Edits keep a live listing live; a listing under admin review or taken down keeps that status.
            if ($from === ProductStatus::ACTIVE && $stock && $locked->stock_quantity === 0) {
                $locked->status = ProductStatus::SOLD;
            } elseif ($from === ProductStatus::SOLD && $stock && $locked->stock_quantity > 0) {
                $locked->status = $this->reopenStatus($locked);
            }
            $locked->save();
            $this->audit->record($user, 'products', $locked->id, 'product.updated', $from->value, $locked->status->value, null);

            return $locked;
        }, 3);
    }

    /**
     * A restocked sold-out listing goes back on sale at once when its shop, owner and category could sell it;
     * otherwise it is HIDDEN, and the seller publishes it again once that is fixed (publishing re-checks all).
     */
    private function reopenStatus(Product $product): ProductStatus
    {
        $shop = $product->shop;
        if (! $shop->owner || ! app(OrderLocks::class)->eligible($shop, $shop->owner, Category::orderBy('id')->get()->keyBy('id'), $product->category_id)) {
            return ProductStatus::HIDDEN;
        }
        $product->published_at ??= now();

        return ProductStatus::ACTIVE;
    }

    public function requirePublishable(Product $product): void
    {
        abort_unless($product->shop->status === ShopStatus::ACTIVE && ! $product->shop->trashed() && $product->shop->owner && $product->shop->owner->status === UserStatus::ACTIVE && ! $product->shop->owner->trashed(), 409, 'Shop and owner must be active.');
        $this->locks->validateCategory($product->category_id);
        $errors = [];
        if ($product->stock_quantity < 1) {
            $errors['stock_quantity'] = ['Stock must be greater than zero.'];
        }
        if (bccomp($product->price, '0', 2) <= 0 || ! $product->name || ! $product->description) {
            $errors['product'] = ['Complete all product fields before submitting.'];
        }
        if (! $product->images()->exists() || $product->images()->where('is_primary', true)->count() !== 1) {
            $errors['images'] = ['At least one image and exactly one primary image are required.'];
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function action(User $user, Product $product, string $action): Product
    {
        return DB::transaction(function () use ($user, $product, $action): Product {
            $locked = $this->locks->lock($user, $product, in_array($action, ['submit', 'reopen'], true) ? 'submit' : ($action === 'hide' ? 'hide' : 'update'));
            $this->claims->assertAvailable($locked->id);
            $from = $locked->status;
            // "submit" publishes at once (no review before sale). A listing an admin took down only comes back
            // through "reopen", which asks an admin; there is no way back to DRAFT that would skip that review.
            $allowed = ['submit' => ['DRAFT', 'HIDDEN'], 'reopen' => ['REJECTED'], 'hide' => ['ACTIVE', 'SOLD']];
            abort_unless(in_array($from->value, $allowed[$action] ?? [], true), 409, 'This action is not allowed in the current product status.');
            if ($action !== 'hide') {
                $this->requirePublishable($locked);
            }
            $locked->status = match ($action) {
                'submit' => ProductStatus::ACTIVE, 'reopen' => ProductStatus::PENDING, default => ProductStatus::HIDDEN
            };
            if ($locked->status === ProductStatus::ACTIVE) {
                $locked->published_at ??= now();
            }
            $locked->save();
            $this->audit->record($user, 'products', $locked->id, 'product.'.$action, $from->value, $locked->status->value, null);

            return $locked;
        }, 3);
    }

    public function moderate(User $user, Product $product, array $data): Product
    {
        return DB::transaction(function () use ($user, $product, $data): Product {
            $locked = $this->locks->lock($user, $product, 'moderate');
            $from = $locked->status->value;
            // PENDING is a listing waiting for an admin (a reopen request, or hidden after reports).
            // Taking a listing down is REJECTED with a reason; the seller can then ask to reopen it.
            $allowed = ['PENDING' => ['ACTIVE', 'REJECTED'], 'ACTIVE' => ['REJECTED'], 'SOLD' => ['REJECTED']];
            abort_unless($data['expected_status'] === $from && in_array($data['status'], $allowed[$from] ?? [], true), 409, 'Product status changed or transition is invalid.');
            $winnerClaim = null;
            if ($data['status'] === ProductStatus::REJECTED->value && $from !== ProductStatus::PENDING->value) {
                // A running auction must be cancelled first so bidders are told; an ended one only loses its checkout.
                $running = Auction::where('product_id', $locked->id)->whereIn('status', [AuctionStatus::SCHEDULED, AuctionStatus::LIVE])->lockForUpdate()->first();
                if ($running) {
                    abort(response()->json(['message' => 'Sản phẩm đang có phiên đấu giá. Hãy hủy phiên trước khi gỡ tin.', 'auction_id' => (string) $running->id], 409));
                }
                $winnerClaim = $this->claims->claims()->where('product_id', $locked->id)->lockForUpdate()->first();
            }
            if ($data['status'] === 'ACTIVE') {
                $this->requirePublishable($locked);
                $locked->published_at ??= now();
            }
            $locked->status = $data['status'];
            $locked->moderation_reason = $data['reason'];
            $locked->save();
            $this->audit->record($user, 'products', $locked->id, 'product.moderated', $from, $locked->status->value, $data['reason']);
            if ($winnerClaim) {
                app(AuctionNotificationService::class)->checkoutUnavailable($winnerClaim->setRelation('product', $locked));
            }

            return $locked;
        }, 3);
    }

    public function delete(User $user, Product $product): void
    {
        DB::transaction(function () use ($user, $product): void {
            $locked = $this->locks->lock($user, $product, 'delete');
            $this->claims->assertAvailable($locked->id);
            $this->audit->record($user, 'products', $locked->id, 'product.deleted', $locked->status->value, $locked->status->value, null);
            $locked->delete();
        }, 3);
    }

    /** Empty input clears the "price before the discount"; any other value is kept with two decimals. */
    private function normalizeCompareAt(array $data): array
    {
        if (array_key_exists('compare_at_price', $data)) {
            $data['compare_at_price'] = $data['compare_at_price'] === null || $data['compare_at_price'] === '' ? null : bcadd((string) $data['compare_at_price'], '0', 2);
        }

        return $data;
    }

    private function assertCompareAt(string $price, ?string $compareAt): void
    {
        if ($compareAt !== null && bccomp($compareAt, bcadd($price, '0', 2), 2) <= 0) {
            throw ValidationException::withMessages(['compare_at_price' => 'Giá trước khi giảm phải cao hơn giá bán.']);
        }
    }
}
