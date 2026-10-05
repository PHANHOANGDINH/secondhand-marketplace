<?php

namespace App\Services;

use App\Enums\CategoryStatus;
use App\Enums\ShopStatus;
use App\Enums\UserStatus;
use App\Models\Category;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class OrderLocks
{
    public function accounts(array $ids): Collection
    {
        return User::withTrashed()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    public function active(User $user): void
    {
        abort_unless(! $user->trashed() && $user->status === UserStatus::ACTIVE, 403);
    }

    public function categories(): Collection
    {
        return Category::orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }

    public function eligible(Shop $shop, User $owner, Collection $categories, int $categoryId): bool
    {
        if ($shop->trashed() || $shop->status !== ShopStatus::ACTIVE || $owner->trashed() || $owner->status !== UserStatus::ACTIVE) {
            return false;
        }
        $seen = [];
        while ($categoryId !== 0) {
            $category = $categories->get($categoryId);
            if (! $category || $category->status !== CategoryStatus::ACTIVE || isset($seen[$categoryId]) || count($seen) >= 3) {
                return false;
            }
            $seen[$categoryId] = true;
            $categoryId = $category->parent_id ?? 0;
        }

        return true;
    }

    public function transaction(callable $callback): mixed
    {
        try {
            return DB::transaction($callback, 3);
        } catch (QueryException $e) {
            if (in_array($e->errorInfo[1] ?? null, [1205, 1213], true) || $e->getCode() === '40001') {
                abort(409, 'Concurrent change. Retry with the same idempotency key or refresh the order.');
            }
            throw $e;
        }
    }
}
