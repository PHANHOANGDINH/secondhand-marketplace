<?php

namespace App\Services;

use App\Enums\ShopOrderStatus;
use App\Enums\ShopStatus;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Collection;

/**
 * Voucher pricing for fixed-price checkout. All money is DECIMAL strings handled with BCMath
 * and every discount is rounded DOWN to a whole đồng. Rules: docs/extension-d6-vouchers-verification.md.
 *
 * Lock order: vouchers are locked by ascending id AFTER every lock the caller already holds
 * (checkout: accounts → cart → order → address → cart items → shops → categories → products → images → vouchers;
 * cancellation: accounts → order → shop orders → shops → categories → products → payments → vouchers).
 */
class VoucherService
{
    public function __construct(private AuditService $audit) {}

    /** Upper-case, trimmed codes; empty input means "no voucher". */
    public static function codes(?string $platform, array $shops): array
    {
        $clean = fn (mixed $code): string => is_string($code) ? strtoupper(trim($code)) : '';
        $byShop = [];
        foreach ($shops as $shopId => $code) {
            if ($clean($code) !== '') {
                $byShop[(int) $shopId] = $clean($code);
            }
        }
        ksort($byShop);

        return [$clean($platform) === '' ? null : $clean($platform), $byShop];
    }

    /**
     * Prices one prospective order. Nothing is written; with `$lock` the vouchers are locked for the caller's transaction.
     *
     * @param  array<int, string>  $subtotals  shop id => shop order subtotal, in the order the shop orders will be created
     * @param  Collection<int, Shop>  $shops  keyed by id
     * @return array{parts: array<int, array<string, mixed>>, platform: ?Voucher, platform_discount: string, subtotal: string, discount: string, total: string, errors: array{platform_voucher: ?string, shop_vouchers: array<int, string>}}
     */
    public function plan(User $buyer, array $subtotals, Collection $shops, ?string $platformCode, array $shopCodes, bool $lock): array
    {
        [$platformCode, $shopCodes] = self::codes($platformCode, $shopCodes);
        $errors = ['platform_voucher' => null, 'shop_vouchers' => []];
        $parts = [];
        foreach ($subtotals as $shopId => $subtotal) {
            $parts[$shopId] = ['subtotal' => $subtotal, 'discount_shop' => '0.00', 'discount_platform' => '0.00', 'voucher' => null];
        }
        $wanted = array_values(array_unique(array_filter([$platformCode, ...$shopCodes])));
        $vouchers = collect();
        $used = collect();
        if ($wanted !== []) {
            $vouchers = Voucher::whereIn('code', $wanted)->orderBy('id')->when($lock, fn ($query) => $query->lockForUpdate())->get()->keyBy('code');
            // The buyer's live (not released) uses of these vouchers, in one query.
            $used = VoucherRedemption::where('user_id', $buyer->id)->whereIn('voucher_id', $vouchers->pluck('id'))->whereNull('released_at')
                ->selectRaw('voucher_id, COUNT(*) AS total')->groupBy('voucher_id')->pluck('total', 'voucher_id');
        }
        $now = now();
        $check = function (?Voucher $voucher, string $slot, ?Shop $shop, string $base) use ($buyer, $used, $now): ?string {
            return match (true) {
                $voucher === null => 'Mã giảm giá không tồn tại.',
                $slot === Voucher::PLATFORM && $voucher->scope !== Voucher::PLATFORM => 'Đây là mã của gian hàng, hãy nhập ở ô mã của gian hàng đó.',
                $slot === Voucher::SHOP && $voucher->scope !== Voucher::SHOP => 'Đây là mã toàn sàn, hãy nhập ở ô mã của sàn.',
                $slot === Voucher::SHOP && (int) $voucher->shop_id !== (int) $shop->id => 'Mã này không thuộc gian hàng này.',
                $voucher->status !== Voucher::ACTIVE => 'Mã giảm giá đã ngừng áp dụng.',
                $now->lt($voucher->starts_at) => 'Mã giảm giá chưa đến thời gian áp dụng.',
                $now->gt($voucher->ends_at) => 'Mã giảm giá đã hết hạn.',
                $voucher->usage_limit !== null && $voucher->used_count >= $voucher->usage_limit => 'Mã giảm giá đã hết lượt sử dụng.',
                $voucher->per_user_limit !== null && (int) ($used[$voucher->id] ?? 0) >= $voucher->per_user_limit => 'Bạn đã dùng hết số lượt cho mã này.',
                $slot === Voucher::SHOP && (int) $shop->owner_id === (int) $buyer->id => 'Không thể dùng mã của gian hàng của chính bạn.',
                $slot === Voucher::SHOP && ($shop->trashed() || $shop->status !== ShopStatus::ACTIVE) => 'Gian hàng hiện không hoạt động.',
                bccomp($base, $voucher->min_order, 2) < 0 => 'Đơn chưa đạt giá trị tối thiểu '.number_format((float) $voucher->min_order, 0, ',', '.').' ₫ để dùng mã này.',
                default => null,
            };
        };

        foreach ($shopCodes as $shopId => $code) {
            if (! isset($parts[$shopId])) {
                $errors['shop_vouchers'][$shopId] = 'Đơn không có sản phẩm của gian hàng này.';

                continue;
            }
            $voucher = $vouchers->get($code);
            if ($error = $check($voucher, Voucher::SHOP, $shops->get($shopId), $parts[$shopId]['subtotal'])) {
                $errors['shop_vouchers'][$shopId] = $error;

                continue;
            }
            $parts[$shopId]['voucher'] = $voucher;
            $parts[$shopId]['discount_shop'] = self::discount($voucher, $parts[$shopId]['subtotal']);
        }

        // The platform voucher is calculated on what is left after the shop vouchers.
        $bases = array_map(fn (array $part): string => bcsub($part['subtotal'], $part['discount_shop'], 2), $parts);
        $base = array_reduce($bases, fn (string $sum, string $value): string => bcadd($sum, $value, 2), '0.00');
        $platform = null;
        $platformDiscount = '0.00';
        if ($platformCode !== null) {
            $voucher = $vouchers->get($platformCode);
            if ($error = $check($voucher, Voucher::PLATFORM, null, $base)) {
                $errors['platform_voucher'] = $error;
            } else {
                $platform = $voucher;
                $platformDiscount = self::discount($voucher, $base);
                foreach (self::allocate($platformDiscount, $bases) as $shopId => $share) {
                    $parts[$shopId]['discount_platform'] = $share;
                }
            }
        }

        $subtotal = $discount = '0.00';
        foreach ($parts as $shopId => $part) {
            $parts[$shopId]['discount'] = bcadd($part['discount_shop'], $part['discount_platform'], 2);
            $parts[$shopId]['total'] = bcsub($part['subtotal'], $parts[$shopId]['discount'], 2);
            $subtotal = bcadd($subtotal, $part['subtotal'], 2);
            $discount = bcadd($discount, $parts[$shopId]['discount'], 2);
            // A payment must be positive, so no shop order may be discounted down to nothing.
            if (bccomp($parts[$shopId]['total'], '0', 2) <= 0 && bccomp($parts[$shopId]['discount'], '0', 2) > 0) {
                $message = 'Số tiền sau khi giảm của mỗi gian hàng phải lớn hơn 0 ₫.';
                if ($part['voucher']) {
                    $errors['shop_vouchers'][$shopId] = $message;
                } else {
                    $errors['platform_voucher'] = $message;
                }
            }
        }

        return ['parts' => $parts, 'platform' => $platform, 'platform_discount' => $platformDiscount, 'subtotal' => $subtotal, 'discount' => $discount, 'total' => bcsub($subtotal, $discount, 2), 'errors' => $errors];
    }

    public static function failed(array $plan): bool
    {
        return $plan['errors']['platform_voucher'] !== null || $plan['errors']['shop_vouchers'] !== [];
    }

    /** PERCENT: base × value / 100, capped by max_discount. FIXED: value. Never above the base; whole đồng, rounded down. */
    public static function discount(Voucher $voucher, string $base): string
    {
        $raw = $voucher->type === Voucher::PERCENT ? bcdiv(bcmul($base, $voucher->value, 4), '100', 4) : $voucher->value;
        if ($voucher->type === Voucher::PERCENT && $voucher->max_discount !== null && bccomp($raw, $voucher->max_discount, 4) > 0) {
            $raw = $voucher->max_discount;
        }
        if (bccomp($raw, $base, 4) > 0) {
            $raw = $base;
        }

        return bcadd($raw, '0', 0).'.00';
    }

    /**
     * Splits a platform discount over the shop orders in proportion to their bases, each share rounded down
     * to a whole đồng. The leftover đồng go to the last shop order (the one that gets the highest id); if that
     * one cannot absorb all of it, the rest moves to the one before it. The shares always add up to `$discount`.
     *
     * @param  array<int, string>  $bases  shop id => base, in creation order
     * @return array<int, string>
     */
    public static function allocate(string $discount, array $bases): array
    {
        $total = array_reduce($bases, fn (string $sum, string $value): string => bcadd($sum, $value, 2), '0.00');
        $shares = array_map(fn (): string => '0', $bases);
        if (bccomp($discount, '0', 2) <= 0 || bccomp($total, '0', 2) <= 0) {
            return array_map(fn (): string => '0.00', $bases);
        }
        $left = bcadd($discount, '0', 0);
        foreach ($bases as $shopId => $base) {
            $shares[$shopId] = bcdiv(bcmul($discount, $base, 4), $total, 0);
            $left = bcsub($left, $shares[$shopId], 0);
        }
        foreach (array_reverse(array_keys($bases)) as $shopId) {
            if (bccomp($left, '0', 0) <= 0) {
                break;
            }
            $room = bcsub(bcadd($bases[$shopId], '0', 0), $shares[$shopId], 0);
            $extra = bccomp($left, $room, 0) > 0 ? $room : $left;
            $shares[$shopId] = bcadd($shares[$shopId], $extra, 0);
            $left = bcsub($left, $extra, 0);
        }

        return array_map(fn (string $share): string => $share.'.00', $shares);
    }

    /** Records the use of every voucher in an accepted plan. The caller holds the voucher locks taken by plan(). */
    public function commit(array $plan, Order $order, User $buyer): void
    {
        $shopOrders = $order->shopOrders()->get()->keyBy('shop_id');
        $record = function (Voucher $voucher, ?ShopOrder $part, string $amount) use ($order, $buyer): void {
            VoucherRedemption::create(['voucher_id' => $voucher->id, 'user_id' => $buyer->id, 'order_id' => $order->id, 'shop_order_id' => $part?->id, 'discount_amount' => $amount]);
            $voucher->increment('used_count');
            $this->audit->record($buyer, 'vouchers', $voucher->id, 'voucher.redeemed', null, $voucher->code, 'order '.$order->order_code.': '.$amount);
        };
        foreach ($plan['parts'] as $shopId => $part) {
            if ($part['voucher']) {
                $record($part['voucher'], $shopOrders[$shopId], $part['discount_shop']);
            }
        }
        if ($plan['platform']) {
            $record($plan['platform'], null, $plan['platform_discount']);
        }
    }

    /**
     * Gives uses back after a shop order was cancelled: its SHOP voucher always, the order's PLATFORM voucher
     * only once every shop order of the order is cancelled. A redemption is released at most once, and the
     * platform discount already split over the shop orders is not redistributed.
     *
     * @param  Collection<int, ShopOrder>  $parts  all shop orders of the order, already locked, with current statuses
     */
    public function release(User $actor, Order $order, ShopOrder $cancelled, Collection $parts): void
    {
        $allCancelled = $parts->every(fn (ShopOrder $part): bool => $part->status === ShopOrderStatus::CANCELLED);
        $redemptions = VoucherRedemption::where('order_id', $order->id)->whereNull('released_at')
            ->where(fn ($query) => $query->where('shop_order_id', $cancelled->id)->when($allCancelled, fn ($or) => $or->orWhereNull('shop_order_id')))
            ->orderBy('id')->get();
        if ($redemptions->isEmpty()) {
            return;
        }
        $vouchers = Voucher::whereIn('id', $redemptions->pluck('voucher_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        foreach ($redemptions as $redemption) {
            // Conditional update: a second cancellation path can never release the same row again.
            if (VoucherRedemption::whereKey($redemption->id)->whereNull('released_at')->update(['released_at' => now()]) !== 1) {
                continue;
            }
            $voucher = $vouchers[$redemption->voucher_id];
            $voucher->update(['used_count' => max(0, $voucher->used_count - 1)]);
            $this->audit->record($actor, 'vouchers', $voucher->id, 'voucher.released', $voucher->code, null, 'order '.$order->order_code);
        }
    }

    /**
     * Read-only price preview of the buyer's cart (or of the selected cart items) with the given codes.
     *
     * @return array<string, mixed>
     */
    public function preview(User $buyer, array $itemIds, ?string $platformCode, array $shopCodes): array
    {
        $items = CartItem::whereHas('cart', fn ($cart) => $cart->where('user_id', $buyer->id))
            ->when($itemIds !== [], fn ($query) => $query->whereIn('id', $itemIds))
            ->with(['product:id,shop_id,price', 'product.shop' => fn ($shop) => $shop->withTrashed()->select('id', 'name', 'slug', 'owner_id', 'status', 'deleted_at')])
            ->orderBy('id')->get();
        $subtotals = [];
        $shops = collect();
        foreach ($items as $item) {
            if (! $item->product?->shop) {
                continue;
            }
            $shop = $item->product->shop;
            $shops[$shop->id] = $shop;
            $subtotals[$shop->id] = bcadd($subtotals[$shop->id] ?? '0.00', bcmul($item->product->price, (string) $item->quantity, 2), 2);
        }
        $plan = $this->plan($buyer, $subtotals, $shops, $platformCode, $shopCodes, false);

        return [
            'shop_orders' => array_values(array_map(fn (int $shopId, array $part): array => [
                'shop_id' => (string) $shopId, 'shop_name' => $shops[$shopId]->name, 'subtotal' => $part['subtotal'],
                'discount_shop' => $part['discount_shop'], 'discount_platform' => $part['discount_platform'], 'total' => $part['total'],
                'voucher_code' => $part['voucher']?->code,
            ], array_keys($plan['parts']), $plan['parts'])),
            'subtotal' => $plan['subtotal'], 'discount_total' => $plan['discount'], 'total' => $plan['total'],
            'platform_voucher_code' => $plan['platform']?->code, 'platform_discount' => $plan['platform_discount'],
            'errors' => ['platform_voucher' => $plan['errors']['platform_voucher'], 'shop_vouchers' => (object) array_combine(array_map('strval', array_keys($plan['errors']['shop_vouchers'])), array_values($plan['errors']['shop_vouchers']))],
        ];
    }
}
