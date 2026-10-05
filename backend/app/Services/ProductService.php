<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Enums\UserStatus;
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
    public function __construct(private ProductLocks $locks, private SlugGenerator $slugs, private AuditService $audit) {}

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
            $from = $locked->status;
            $locked->fill($data);
            if (! $locked->isDirty()) {
                return $locked;
            }
            $content = $locked->isDirty(['name', 'description', 'category_id', 'price', 'condition']);
            $stock = $locked->isDirty('stock_quantity');
            if ($from === ProductStatus::ACTIVE && $stock && $locked->stock_quantity === 0) {
                $locked->status = ProductStatus::SOLD;
            } elseif (($content && in_array($from, [ProductStatus::ACTIVE, ProductStatus::HIDDEN], true)) || ($stock && $locked->stock_quantity > 0 && in_array($from, [ProductStatus::ACTIVE, ProductStatus::SOLD], true))) {
                $locked->status = ProductStatus::PENDING;
            }
            $locked->save();
            $this->audit->record($user, 'products', $locked->id, 'product.updated', $from->value, $locked->status->value, null);

            return $locked;
        }, 3);
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
            $locked = $this->locks->lock($user, $product, $action === 'submit' ? 'submit' : ($action === 'hide' ? 'hide' : 'update'));
            $from = $locked->status;
            $allowed = ['submit' => ['DRAFT', 'HIDDEN'], 'withdraw' => ['PENDING'], 'revise' => ['REJECTED'], 'hide' => ['ACTIVE', 'SOLD']];
            abort_unless(in_array($from->value, $allowed[$action], true), 409, 'This action is not allowed in the current product status.');
            if ($action === 'submit') {
                $this->requirePublishable($locked);
            }
            $locked->status = match ($action) {
                'submit' => ProductStatus::PENDING,'hide' => ProductStatus::HIDDEN,default => ProductStatus::DRAFT
            };
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
            $allowed = ['PENDING' => ['ACTIVE', 'REJECTED'], 'ACTIVE' => ['HIDDEN'], 'SOLD' => ['HIDDEN']];
            abort_unless($data['expected_status'] === $from && in_array($data['status'], $allowed[$from] ?? [], true), 409, 'Product status changed or transition is invalid.');
            if ($data['status'] === 'ACTIVE') {
                $this->requirePublishable($locked);
                $locked->published_at = now();
            }
            $locked->status = $data['status'];
            $locked->moderation_reason = $data['reason'];
            $locked->save();
            $this->audit->record($user, 'products', $locked->id, 'product.moderated', $from, $locked->status->value, $data['reason']);

            return $locked;
        }, 3);
    }

    public function delete(User $user, Product $product): void
    {
        DB::transaction(function () use ($user, $product): void {
            $locked = $this->locks->lock($user, $product, 'delete');
            $this->audit->record($user, 'products', $locked->id, 'product.deleted', $locked->status->value, $locked->status->value, null);
            $locked->delete();
        }, 3);
    }
}
