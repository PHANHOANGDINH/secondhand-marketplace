<?php

namespace App\Services;

use App\Enums\AuctionStatus;
use App\Enums\CategoryStatus;
use App\Enums\ProductStatus;
use App\Enums\ShopStatus;
use App\Enums\UserStatus;
use App\Events\AuctionBidPlaced;
use App\Events\AuctionOutbid;
use App\Events\AuctionStatusChanged;
use App\Http\Requests\Domain\StoreAuctionRequest;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class AuctionService
{
    public function __construct(private ProductQuery $products) {}

    public function publicQuery(): Builder
    {
        return Auction::whereIn('product_id', $this->products->publicVisible()->select('products.id'))->where('status', '!=', AuctionStatus::CANCELLED)->with('product:id,name,slug')->withCount('bids');
    }

    /**
     * Lock in the marketplace's user -> shop -> category -> product order before the auction.
     *
     * @return array{Product, Shop, ?User, bool}
     */
    private function context(int $productId, ?User $actor = null): array
    {
        $product = Product::withTrashed()->findOrFail($productId);
        $shop = Shop::withTrashed()->findOrFail($product->shop_id);
        $users = User::withTrashed()->whereIn('id', array_filter([$actor?->id, $shop->owner_id]))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $shop = Shop::withTrashed()->whereKey($shop->id)->lockForUpdate()->firstOrFail();
        $categories = Category::orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $product = Product::withTrashed()->whereKey($productId)->lockForUpdate()->firstOrFail();
        $visible = ! $product->trashed() && $product->status === ProductStatus::ACTIVE && $product->stock_quantity > 0
            && ! $shop->trashed() && $shop->status === ShopStatus::ACTIVE
            && isset($users[$shop->owner_id]) && ! $users[$shop->owner_id]->trashed() && $users[$shop->owner_id]->status === UserStatus::ACTIVE;
        $categoryId = $product->category_id;
        for ($depth = 0; $categoryId !== null && $depth < 3; $depth++) {
            $category = $categories->get($categoryId);
            if (! $category || $category->status !== CategoryStatus::ACTIVE) {
                $visible = false;
                break;
            }
            $categoryId = $category->parent_id;
        }
        $visible = $visible && $categoryId === null;

        return [$product, $shop, $actor ? $users->get($actor->id) : null, $visible];
    }

    public function create(User $seller, array $data): Auction
    {
        $data = Validator::make($data, (new StoreAuctionRequest)->rules())->validate();
        try {
            return DB::transaction(function () use ($seller, $data): Auction {
                [$product, $shop, $actor, $visible] = $this->context((int) $data['product_id'], $seller);
                abort_unless($actor, 403);
                Gate::forUser($actor)->authorize('create', Auction::class);
                abort_unless($shop->owner_id === $actor->id, 403, 'You do not own this product.');
                abort_unless($visible, 409, 'Product and shop must be publicly available.');
                $time = CarbonImmutable::now();
                $start = CarbonImmutable::parse($data['starts_at'])->utc();
                $end = CarbonImmutable::parse($data['ends_at'])->utc();
                abort_unless($end->greaterThan($time) && $end->greaterThan($start), 422, 'Auction must end in the future and after its start.');
                app(FixedPriceEligibility::class)->assertAvailable($product->id);
                $auction = new Auction;
                $auction->forceFill(['product_id' => $product->id, 'seller_id' => $actor->id, 'start_price' => bcadd($data['start_price'], '0', 2), 'current_price' => bcadd($data['start_price'], '0', 2), 'bid_increment' => bcadd($data['bid_increment'], '0', 2), 'starts_at' => $start, 'ends_at' => $end, 'status' => $start->lessThanOrEqualTo($time) ? AuctionStatus::LIVE : AuctionStatus::SCHEDULED])->save();

                return $auction;
            }, 3);
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Product already has an unfinished auction.');
        }
    }

    public function placeBid(User $bidder, Auction $auction, string $amount): Bid
    {
        Validator::make(['amount' => $amount], ['amount' => ['required', new DecimalPrice]])->validate();

        return DB::transaction(function () use ($bidder, $auction, $amount): Bid {
            [$product, $shop, $actor, $visible] = $this->context($auction->product_id, $bidder);
            $locked = Auction::whereKey($auction->id)->lockForUpdate()->firstOrFail();
            abort_unless($actor, 403);
            Gate::forUser($actor)->authorize('bid', $locked);
            abort_unless($visible && $shop->owner_id === $locked->seller_id, 409, 'Product and seller are unavailable.');
            $time = CarbonImmutable::now();
            abort_unless($locked->status === AuctionStatus::LIVE && $time->greaterThanOrEqualTo($locked->starts_at) && $time->lessThan($locked->ends_at), 409, 'Auction is not live.');
            $previous = $locked->bids()->orderByDesc('id')->lockForUpdate()->first();
            $minimum = $previous ? bcadd($locked->current_price, $locked->bid_increment, 2) : $locked->start_price;
            abort_if(bccomp($minimum, '9999999999999.99', 2) > 0 || bccomp($amount, $minimum, 2) < 0, 422, 'Bid is below the minimum supported next bid.');
            $bid = $locked->bids()->make();
            $bid->forceFill(['user_id' => $actor->id, 'amount' => bcadd($amount, '0', 2)])->save();
            $locked->forceFill(['current_price' => $bid->amount])->save();
            $count = (int) $locked->bids()->selectRaw('COUNT(*) AS total')->lockForUpdate()->first()->total;
            event(new AuctionBidPlaced([
                'auction_id' => (string) $locked->id, 'current_price' => $locked->current_price, 'minimum_next_bid' => self::minimumNextBid($locked, $count), 'bid_count' => $count,
                'latest_bid' => ['id' => (string) $bid->id, 'bidder_label' => 'Người mua #'.$actor->id, 'amount' => $bid->amount, 'created_at' => $bid->created_at->toISOString()],
                'updated_at' => $locked->updated_at->toISOString(), 'server_time' => now()->toISOString(),
            ]));
            if ($previous && $previous->user_id !== $actor->id) {
                app(AuctionNotificationService::class)->outbid($locked->setRelation('product', $product), $bid, $previous);
                event(new AuctionOutbid($previous->user_id, [
                    'auction_id' => (string) $locked->id, 'product' => ['id' => (string) $product->id, 'name' => $product->name],
                    'new_current_price' => $locked->current_price, 'minimum_next_bid' => self::minimumNextBid($locked, $count), 'server_time' => now()->toISOString(),
                ]));
            }

            return $bid;
        }, 3);
    }

    public function start(Auction $auction): Auction
    {
        return DB::transaction(function () use ($auction): Auction {
            [, $shop, , $visible] = $this->context($auction->product_id);
            $locked = Auction::whereKey($auction->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== AuctionStatus::SCHEDULED || now()->lessThan($locked->starts_at)) {
                return $locked;
            }
            abort_if(now()->greaterThanOrEqualTo($locked->ends_at), 409, 'Auction has expired; finalize it instead.');
            abort_unless($visible && $shop->owner_id === $locked->seller_id, 409, 'Product and seller are unavailable.');
            $locked->forceFill(['status' => AuctionStatus::LIVE])->save();
            $this->statusChanged($locked);

            return $locked;
        }, 3);
    }

    public function cancel(User $seller, Auction $auction): Auction
    {
        return DB::transaction(function () use ($seller, $auction): Auction {
            $actor = User::whereKey($seller->id)->lockForUpdate()->firstOrFail();
            $locked = Auction::whereKey($auction->id)->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('cancel', $locked);
            if ($locked->status === AuctionStatus::CANCELLED) {
                return $locked;
            }
            abort_unless(in_array($locked->status, [AuctionStatus::SCHEDULED, AuctionStatus::LIVE], true) && now()->lessThan($locked->ends_at) && ! $locked->bids()->lockForUpdate()->first(['id']), 409, 'Auction cannot be cancelled after bidding or expiry.');
            $locked->forceFill(['status' => AuctionStatus::CANCELLED])->save();
            app(AuctionNotificationService::class)->cancelled($locked);
            $this->statusChanged($locked);

            return $locked;
        }, 3);
    }

    public function finalize(Auction $auction): Auction
    {
        return DB::transaction(function () use ($auction): Auction {
            $locked = Auction::whereKey($auction->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [AuctionStatus::ENDED, AuctionStatus::CANCELLED], true)) {
                return $locked;
            }
            abort_unless(now()->greaterThanOrEqualTo($locked->ends_at), 409, 'Auction has not ended.');
            $winner = $locked->bids()->orderByDesc('amount')->orderBy('id')->lockForUpdate()->first();
            $locked->forceFill(['status' => AuctionStatus::ENDED, 'winning_bid_id' => $winner?->id, 'winner_user_id' => $winner?->user_id, 'winner_checkout_expires_at' => $winner ? CarbonImmutable::now()->addHours(max(1, (int) config('auctions.winner_checkout_hours', 24))) : null])->save();
            app(AuctionNotificationService::class)->finalized($locked);
            $this->statusChanged($locked);

            return $locked;
        }, 3);
    }

    public static function minimumNextBid(Auction $auction, int $bidCount): ?string
    {
        if (in_array($auction->status, [AuctionStatus::ENDED, AuctionStatus::CANCELLED], true)) {
            return null;
        }
        $minimum = $bidCount > 0 ? bcadd($auction->current_price, $auction->bid_increment, 2) : $auction->start_price;

        return bccomp($minimum, '9999999999999.99', 2) > 0 ? null : $minimum;
    }

    private function statusChanged(Auction $auction): void
    {
        event(new AuctionStatusChanged(['auction_id' => (string) $auction->id, 'status' => $auction->status->value, 'starts_at' => $auction->starts_at->toISOString(), 'ends_at' => $auction->ends_at->toISOString(), 'current_price' => $auction->current_price, 'updated_at' => $auction->updated_at->toISOString(), 'server_time' => now()->toISOString()]));
    }
}
