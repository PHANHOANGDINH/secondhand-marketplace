<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CartService
{
    public function __construct(private ProductQuery $products) {}

    private function activeBuyer(User $user): void
    {
        abort_unless($user->status === UserStatus::ACTIVE && ! $user->trashed(), 403);
    }

    /** Caller holds the buyer row lock, including when the cart does not exist. */
    private function lockedCart(User $buyer): Cart
    {
        return $buyer->cart()->lockForUpdate()->first() ?? $buyer->cart()->create();
    }

    /** @return array{cart: Cart, items: array, total_quantity: int, subtotal: string, available_subtotal: string} */
    public function read(User $user): array
    {
        return DB::transaction(function () use ($user): array {
            $buyer = User::withTrashed()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->activeBuyer($buyer);
            $cart = $this->lockedCart($buyer);
            $items = $this->describe($cart->items()->orderBy('id')->get(), $buyer);
            $subtotal = '0.00';
            $availableSubtotal = '0.00';
            $quantity = 0;
            foreach ($items as $row) {
                $subtotal = bcadd($subtotal, $row['subtotal'] ?? '0.00', 2);
                $quantity += $row['item']->quantity;
                if ($row['reason'] === null) {
                    $availableSubtotal = bcadd($availableSubtotal, $row['subtotal'], 2);
                }
            }

            return ['cart' => $cart, 'items' => $items, 'total_quantity' => $quantity, 'subtotal' => $subtotal, 'available_subtotal' => $availableSubtotal];
        }, 3);
    }

    /**
     * @param  Collection<int, CartItem>  $items
     * @return array<int, array{item: CartItem, public: bool, reason: ?string, subtotal: ?string}>
     */
    private function describe(Collection $items, User $buyer): array
    {
        $items->load([
            'product' => fn (BelongsTo $relation) => $relation->withTrashed(),
            'product.shop' => fn (BelongsTo $relation) => $relation->withTrashed(),
            'product.images' => fn (HasMany $relation) => $relation->where('is_primary', true)->orderBy('sort_order')->orderBy('id'),
        ]);
        $visible = $this->products->publicVisible()->whereIn('id', $items->pluck('product_id'))->pluck('id')->flip();

        return $items->map(function (CartItem $item) use ($buyer, $visible): array {
            $product = $item->product;
            $public = $product !== null && $visible->has($product->id);
            $reason = match (true) {
                $product?->shop?->owner_id === $buyer->id => 'OWN_SHOP',
                ! $public => 'PRODUCT_UNAVAILABLE',
                $item->quantity > $product->stock_quantity => 'INSUFFICIENT_STOCK',
                default => null,
            };

            return ['item' => $item, 'public' => $public, 'reason' => $reason, 'subtotal' => $product ? bcmul($product->price, (string) $item->quantity, 2) : null];
        })->all();
    }

    /** @return array{item: array, created: bool} */
    public function add(User $user, int $productId, int $quantity): array
    {
        $product = Product::withTrashed()->findOrFail($productId);
        [$item, $created] = $this->write($user, $product, $quantity);

        return ['item' => $this->describe(new Collection([$item]), $user)[0], 'created' => $created];
    }

    /** @return array{item: CartItem, public: bool, reason: ?string, subtotal: ?string} */
    public function update(User $user, CartItem $item, int $quantity): array
    {
        Gate::forUser($user)->authorize('update', $item);
        $product = Product::withTrashed()->findOrFail($item->product_id);
        [$saved] = $this->write($user, $product, $quantity, $item);

        return $this->describe(new Collection([$saved]), $user)[0];
    }

    /** @return array{0: CartItem, 1: bool} */
    private function write(User $user, Product $product, int $quantity, ?CartItem $existing = null): array
    {
        $ownerId = Shop::withTrashed()->whereKey($product->shop_id)->value('owner_id');

        return DB::transaction(function () use ($user, $product, $quantity, $existing, $ownerId): array {
            // Match Product writer lock order; use locking reads until availability is validated.
            $accounts = User::withTrashed()->whereIn('id', [$user->id, $ownerId])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            $buyer = $accounts[$user->id];
            $this->activeBuyer($buyer);
            $cart = $this->lockedCart($buyer);
            $shop = Shop::withTrashed()->whereKey($product->shop_id)->lockForUpdate()->firstOrFail();
            abort_if($shop->owner_id === $buyer->id, 403, 'You cannot add products from your own shop.');
            Category::orderBy('id')->lockForUpdate()->get();
            $locked = Product::withTrashed()->whereKey($product->id)->lockForUpdate()->firstOrFail();
            $item = $cart->items()->where('product_id', $locked->id)->lockForUpdate()->first();
            if ($existing !== null) {
                abort_unless($item && $item->id === $existing->id, 404);
            }
            abort_unless($this->products->publicVisible()->whereKey($locked->id)->exists(), 409, 'Product is no longer available. Refresh your cart.');
            $next = $existing === null ? ($item?->quantity ?? 0) + $quantity : $quantity;
            if ($next < 1 || $next > 99) {
                throw ValidationException::withMessages(['quantity' => ['The resulting quantity must be between 1 and 99.']]);
            }
            abort_if($next > $locked->stock_quantity, 409, 'Requested quantity exceeds current stock. Refresh your cart.');
            $created = $item === null;
            if ($created) {
                $item = $cart->items()->create(['product_id' => $locked->id, 'quantity' => $next]);
            } else {
                $item->update(['quantity' => $next]);
            }
            $cart->touch();

            return [$item, $created];
        }, 3);
    }

    public function remove(User $user, CartItem $item): void
    {
        Gate::forUser($user)->authorize('delete', $item);
        DB::transaction(function () use ($user, $item): void {
            $buyer = User::withTrashed()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->activeBuyer($buyer);
            $cart = $this->lockedCart($buyer);
            $cart->items()->whereKey($item->id)->lockForUpdate()->firstOrFail()->delete();
            $cart->touch();
        }, 3);
    }

    public function clear(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $buyer = User::withTrashed()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            $this->activeBuyer($buyer);
            $cart = $this->lockedCart($buyer);
            $cart->items()->delete();
            $cart->touch();
        }, 3);
    }
}
