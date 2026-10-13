<?php

namespace App\Services;

use App\Enums\ProductStatus;
use App\Enums\ProductVideoStatus;
use App\Enums\ReviewStatus;
use App\Models\Product;
use App\Models\Review;
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
        $user = request()->user('sanctum');
        if ($user) {
            $query->withExists(['wishlistEntries as is_wishlisted' => fn (Builder $rows) => $rows->where('user_id', $user->id)]);
        }

        $query->withExists(['videos as has_video' => $this->publicVideos()]);

        return ReviewService::withSummary($query)->with(['auctionClaim:id,product_id,status,current_price', 'shop' => fn (BelongsTo $q) => $this->sellerStats($q->withTrashed()->select('id', 'name', 'slug', 'owner_id', 'status', 'deleted_at', 'created_at')), 'category:id,name,slug', 'images' => fn (HasMany $q) => $q->orderBy('sort_order')->orderBy('id')]);
    }

    /** Seller-card figures for the eager-loaded shop: published reviews across all of its products, as two subselects. */
    private function sellerStats(BelongsTo $shops): BelongsTo
    {
        $reviews = fn (string $aggregate) => Review::query()->selectRaw($aggregate)
            ->join('products as rated', 'rated.id', '=', 'reviews.product_id')
            ->whereColumn('rated.shop_id', 'shops.id')->where('reviews.status', ReviewStatus::PUBLISHED);

        return $shops->addSelect(['rating_count' => $reviews('COUNT(*)'), 'rating_avg' => $reviews('AVG(reviews.rating)')]);
    }

    /** One definition of "has a video": shared by the has_video filter and the has_video flag. */
    private function publicVideos(): \Closure
    {
        return fn (Builder $videos) => $videos->where('status', ProductVideoStatus::Ready);
    }

    public function apply(Builder $query, array $filters, bool $descendants = false): Builder
    {
        if (($keyword = $filters['q'] ?? $filters['search'] ?? $filters['keyword'] ?? null) !== null && $keyword !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword).'%';
            $query->where(function (Builder $q) use ($pattern, $descendants): void {
                $q->whereRaw("name LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("description LIKE ? ESCAPE '!'", [$pattern]);
                if ($descendants) {
                    $q->orWhereHas('category', fn (Builder $related) => $related->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]))
                        ->orWhereHas('shop', fn (Builder $related) => $related->whereRaw("name LIKE ? ESCAPE '!'", [$pattern]));
                }
            });
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
        if ($descendants && isset($filters['has_video'])) {
            $query->has('videos', (bool) $filters['has_video'] ? '>=' : '<', 1, 'and', $this->publicVideos());
        }
        if ($descendants && isset($filters['sale_type'])) {
            if ($filters['sale_type'] === 'auction') {
                $query->whereHas('auctionClaim');
            } else {
                $query->whereDoesntHave('auctionClaim');
            }
        }
        $query = $this->related($query);
        if ($descendants && ($filters['sort'] ?? '') === 'relevance' && $keyword !== null && $keyword !== '') {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $keyword);
            $query->orderByRaw("CASE WHEN name = ? THEN 0 WHEN name LIKE ? ESCAPE '!' THEN 1 WHEN name LIKE ? ESCAPE '!' THEN 2 ELSE 3 END", [$keyword, $escaped.'%', '%'.$escaped.'%']);
        }
        match ($filters['sort'] ?? 'newest') {
            'oldest' => $query->orderBy('created_at')->orderBy('id'),
            'rating' => $query->orderByDesc('average_rating')->orderByDesc('review_count')->orderByDesc('id'),
            'price_asc' => $query->orderBy('price')->orderBy('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            default => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        return $query;
    }
}
