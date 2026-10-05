<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductQuery
{
    public function __construct(private CategoryService $categories, private ShopService $shops) {}

    public function publicVisible(): Builder
    {
        return Product::where('status', ProductStatus::ACTIVE)->where('stock_quantity', '>', 0)
            ->whereIn('shop_id', $this->shops->publicQuery()->select('id'))
            ->whereIn('category_id', $this->categories->publicQuery()->select('id'));
    }

    public function related(Builder $query): Builder
    {
        return ReviewService::withSummary($query)->with(['shop' => fn (BelongsTo $q) => $q->withTrashed()->select('id', 'name', 'slug', 'owner_id', 'status', 'deleted_at'), 'category:id,name,slug', 'images' => fn (HasMany $q) => $q->orderBy('sort_order')->orderBy('id')]);
    }

    public function apply(Builder $query, array $filters, bool $descendants = false): Builder
    {
        if (($keyword = $filters['search'] ?? $filters['keyword'] ?? null) !== null && $keyword !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword).'%';
            $query->where(fn (Builder $q) => $q->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("description LIKE ? ESCAPE '!'", [$pattern]));
        }
        foreach (['status', 'condition'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['shop'])) {
            $query->where('shop_id', $filters['shop']);
        }
        if (isset($filters['category'])) {
            $ids = [(int) $filters['category']];
            $parents = $ids;
            if ($descendants) {
                for ($level = 0; $level < 2; $level++) {
                    $parents = $this->categories->publicQuery()->whereIn('parent_id', $parents)->pluck('id')->all();
                    $ids = [...$ids, ...$parents];
                }
            }
            $query->whereIn('category_id', $ids);
        }
        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }
        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }
        match ($filters['sort'] ?? 'newest') {
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return $this->related($query);
    }
}
