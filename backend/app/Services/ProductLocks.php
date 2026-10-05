<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ProductLocks
{
    public function lock(User $user, Product $product, string $ability): Product
    {
        $shop = Shop::withTrashed()->findOrFail($product->shop_id);
        $accounts = User::withTrashed()->whereIn('id', [$user->id, $shop->owner_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
        $shop = Shop::withTrashed()->whereKey($shop->id)->lockForUpdate()->firstOrFail();
        Category::orderBy('id')->lockForUpdate()->get();
        $locked = Product::whereKey($product->id)->lockForUpdate()->firstOrFail();
        $locked->setRelation('shop', $shop->setRelation('owner', $accounts[$shop->owner_id]));
        Gate::forUser($accounts[$user->id])->authorize($ability, $locked);

        return $locked;
    }

    public function validateCategory(int|string $id): void
    {
        if (! app(CategoryService::class)->publicQuery()->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['category_id' => ['Category and all ancestors must be active.']]);
        }
    }
}
