<?php

namespace App\Services;

use App\Enums\ShopOrderStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\SearchLog;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic hybrid ranking: no randomness, a fixed number of queries, and every
 * weight lives in config/recommendations.php. Formula: docs/recommendation-scoring.md.
 */
class RecommendationService
{
    public function __construct(private ProductQuery $products) {}

    /** Ranked public products for one user (or guests), each carrying a Vietnamese `reason_label`. */
    public function forUser(?User $user, int $limit, ?string $province = null): Collection
    {
        // Only ids and reasons are cached, per user; the products themselves are re-read so
        // visibility, price and is_wishlisted are always current.
        $ranked = Cache::remember(
            // Without an area the key and the ranking are exactly what they were before the area filter existed.
            'recommendations:v1:'.($user?->id ?? 'guest').($province === null ? '' : ':p'.$province),
            now()->addMinutes((int) config('recommendations.cache_minutes')),
            fn (): array => $this->rank($user, $province),
        );
        if ($ranked === []) {
            return new Collection;
        }
        $rows = $this->products->related($this->products->publicVisible())->whereIn('products.id', array_column($ranked, 'id'))->get()->keyBy('id');
        $result = new Collection;
        foreach ($ranked as $entry) {
            $product = $rows->get($entry['id']);
            if (! $product || ($user && (int) $product->shop?->owner_id === (int) $user->id)) {
                continue;
            }
            $result->push($product->setAttribute('reason_label', $entry['reason']));
            if ($result->count() === $limit) {
                break;
            }
        }

        return $result;
    }

    /** Same or parent category, price within the configured tolerance, same shop first. */
    public function similar(Product $product, int $limit, ?string $province = null): Collection
    {
        $tolerance = (string) config('recommendations.similar_price_tolerance');
        $categories = array_values(array_filter([$product->category_id, Category::whereKey($product->category_id)->value('parent_id')]));

        return $this->products->related($this->products->publicVisible())
            ->whereKeyNot($product->id)
            ->when($province !== null, fn (Builder $query) => $this->products->inProvince($query, $province))
            ->whereIn('category_id', $categories)
            ->whereBetween('price', [bcmul($product->price, bcsub('1', $tolerance, 2), 2), bcmul($product->price, bcadd('1', $tolerance, 2), 2)])
            ->orderByRaw('shop_id = ? DESC', [$product->shop_id])
            ->orderByRaw('category_id = ? DESC', [$product->category_id])
            ->orderByRaw('ABS(price - ?)', [$product->price])
            ->orderBy('id')
            ->limit($limit)->get();
    }

    /** @return list<array{id: int, reason: string}> */
    private function rank(?User $user, ?string $province = null): array
    {
        $config = config('recommendations');
        $now = now();
        $since = $now->copy()->subDays((int) $config['window_days']);
        $candidates = $this->candidates($user, $since, $config, $province);
        if ($candidates->isEmpty()) {
            return [];
        }
        $categories = Category::get(['id', 'parent_id', 'name'])->keyBy('id');
        [$categorySignal, $shopSignal, $searches] = $user ? $this->signals($user, $since, $config) : [[], [], []];
        $personal = $categorySignal !== [] || $shopSignal !== [] || $searches !== [];
        $weights = array_merge(array_fill_keys(['category', 'keyword', 'popularity', 'recency', 'shop', 'rating'], 0.0), $personal ? $config['weights'] : $config['cold_start_weights']);

        $reviews = $candidates->sum('review_count');
        $priorMean = $reviews > 0
            ? $candidates->sum(fn (Product $p): float => (float) $p->average_rating * (int) $p->review_count) / $reviews
            : (float) $config['rating_prior_mean'];
        $priorWeight = (float) $config['rating_prior_weight'];
        $halfSaturation = (float) $config['affinity_half_saturation'];
        $categoryAffinity = [];
        $ranked = [];
        foreach ($candidates as $product) {
            $categoryId = (int) $product->category_id;
            $categoryAffinity[$categoryId] ??= $this->categoryAffinity($categoryId, $categorySignal, $categories, (float) $config['related_category_factor']);
            [$categoryPoints, $interest] = $categoryAffinity[$categoryId];
            $shopPoints = $shopSignal[(int) $product->shop_id] ?? 0;
            [$keyword, $matched] = $this->keywordMatch($searches, mb_strtolower($product->name.' '.($categories->get($categoryId)?->name ?? '')));
            $count = (int) $product->review_count;
            $bayesian = ($count * (float) $product->average_rating + $priorWeight * $priorMean) / ($count + $priorWeight);
            $activity = (int) $product->wishlists_recent + (int) $product->orders_recent;
            $ageDays = max(0, ($product->published_at ?? $product->created_at)->diffInSeconds($now, false)) / 86400;
            $parts = [
                'category' => $weights['category'] * $categoryPoints / ($categoryPoints + $halfSaturation),
                'keyword' => $weights['keyword'] * $keyword,
                'shop' => $weights['shop'] * $shopPoints / ($shopPoints + $halfSaturation),
                'rating' => $weights['rating'] * ($bayesian - 1) / 4,
                'popularity' => $weights['popularity'] * min(1, log(1 + $activity) / log(1 + (int) $config['popularity_cap'])),
                'recency' => $weights['recency'] * 0.5 ** ($ageDays / (float) $config['recency_half_life_days']),
            ];
            $ranked[] = [
                'id' => (int) $product->id,
                'score' => round(array_sum($parts), 6),
                'reason' => $this->reason($parts, [
                    'category' => $categories->get($interest)?->name,
                    'keyword' => $matched,
                    'shop' => $product->shop?->name,
                    'rated' => $count > 0 && $bayesian >= 4,
                    'popular' => $activity > 0,
                    'fresh' => $ageDays <= (float) $config['recency_half_life_days'],
                ]),
            ];
        }
        // Highest score first; equal scores fall back to the lower id, so the order never depends on the database.
        usort($ranked, fn (array $a, array $b): int => [$b['score'], $a['id']] <=> [$a['score'], $b['id']]);

        return array_map(fn (array $row): array => ['id' => $row['id'], 'reason' => $row['reason']], array_slice($ranked, 0, (int) $config['max_results']));
    }

    /** At most `candidate_limit` public products outside the user's own shop: the most popular first, then the newest. */
    private function candidates(?User $user, Carbon $since, array $config, ?string $province = null): Collection
    {
        $ownShops = $user ? Shop::withTrashed()->where('owner_id', $user->id)->pluck('id')->all() : [];
        $base = fn (): Builder => $this->products->publicVisible()->when($ownShops !== [], fn (Builder $query) => $query->whereNotIn('shop_id', $ownShops))
            ->when($province !== null, fn (Builder $query) => $this->products->inProvince($query, $province));
        $activity = DB::table('wishlists')->select('product_id')->where('created_at', '>=', $since)
            ->unionAll($this->orderLines($since)->select('order_items.product_id'));
        $popular = $base()
            ->joinSub(DB::query()->fromSub($activity, 'activity')->selectRaw('product_id, COUNT(*) AS total')->groupBy('product_id'), 'popular', 'popular.product_id', '=', 'products.id')
            ->orderByDesc('popular.total')->orderBy('products.id')->limit((int) $config['popular_candidates'])->pluck('products.id');
        $newest = $base()->orderByDesc('created_at')->orderByDesc('id')->limit((int) $config['candidate_limit'])->pluck('id');
        $ids = $popular->merge($newest)->unique()->take((int) $config['candidate_limit'])->values();

        return ReviewService::withSummary(Product::query()->whereIn('id', $ids)->select(['id', 'shop_id', 'category_id', 'name', 'created_at', 'published_at']))
            ->withCount([
                'wishlistEntries as wishlists_recent' => fn (Builder $rows) => $rows->where('created_at', '>=', $since),
                'orderItems as orders_recent' => fn (Builder $rows) => $rows->where('order_items.created_at', '>=', $since)
                    ->whereHas('shopOrder', fn (Builder $parts) => $parts->where('status', '!=', ShopOrderStatus::CANCELLED)),
            ])
            ->with('shop:id,name')->get();
    }

    private function orderLines(Carbon $since): \Illuminate\Database\Query\Builder
    {
        return DB::table('order_items')->join('shop_orders', 'shop_orders.id', '=', 'order_items.shop_order_id')
            ->where('shop_orders.status', '!=', ShopOrderStatus::CANCELLED->value)->where('order_items.created_at', '>=', $since);
    }

    /** @return array{0: array<int, int>, 1: array<int, int>, 2: list<array{text: string, tokens: list<string>}>} */
    private function signals(User $user, Carbon $since, array $config): array
    {
        $category = $shop = [];
        $sources = [
            'view' => DB::table('product_views')->join('products', 'products.id', '=', 'product_views.product_id')
                ->where('product_views.user_id', $user->id)->where('product_views.viewed_at', '>=', $since),
            'wishlist' => DB::table('wishlists')->join('products', 'products.id', '=', 'wishlists.product_id')
                ->where('wishlists.user_id', $user->id)->where('wishlists.created_at', '>=', $since),
            'order' => $this->orderLines($since)->join('orders', 'orders.id', '=', 'shop_orders.order_id')
                ->join('products', 'products.id', '=', 'order_items.product_id')->where('orders.buyer_id', $user->id),
        ];
        foreach ($sources as $signal => $query) {
            $points = (int) $config['signals'][$signal];
            foreach ($query->groupBy('products.category_id', 'products.shop_id')->selectRaw('products.category_id, products.shop_id, COUNT(*) AS total')->get() as $row) {
                $category[(int) $row->category_id] = ($category[(int) $row->category_id] ?? 0) + $points * (int) $row->total;
                $shop[(int) $row->shop_id] = ($shop[(int) $row->shop_id] ?? 0) + $points * (int) $row->total;
            }
        }
        $searches = [];
        // Repeated identical searches count once; the newest five distinct ones are used.
        foreach (SearchLog::where('user_id', $user->id)->orderByDesc('created_at')->orderByDesc('id')->limit(25)->pluck('query') as $text) {
            $key = mb_strtolower($text);
            $tokens = array_values(array_unique(array_filter(preg_split('/[^\p{L}\p{N}]+/u', $key, -1, PREG_SPLIT_NO_EMPTY) ?: [], fn (string $token): bool => mb_strlen($token) >= 2)));
            if ($tokens !== [] && ! isset($searches[$key]) && count($searches) < (int) $config['recent_searches']) {
                $searches[$key] = ['text' => $text, 'tokens' => $tokens];
            }
        }

        return [$category, $shop, array_values($searches)];
    }

    /**
     * Interaction points for a candidate's category: its own points in full, plus a share of the
     * points of its parent, children and siblings. Also returns the category the user showed most interest in.
     *
     * @return array{0: float, 1: int|null}
     */
    private function categoryAffinity(int $categoryId, array $signal, \Illuminate\Support\Collection $categories, float $factor): array
    {
        $parentOf = fn (int $id): ?int => ($value = $categories->get($id)?->parent_id) === null ? null : (int) $value;
        $parent = $parentOf($categoryId);
        $points = (float) ($signal[$categoryId] ?? 0);
        [$interest, $strongest] = [$points > 0 ? $categoryId : null, $points];
        ksort($signal);
        foreach ($signal as $other => $value) {
            $otherParent = $parentOf($other);
            if ($other === $categoryId || ! ($otherParent === $categoryId || $parent === $other || ($parent !== null && $parent === $otherParent))) {
                continue;
            }
            $points += $factor * $value;
            if ($factor * $value > $strongest) {
                [$interest, $strongest] = [$other, $factor * $value];
            }
        }

        return [$points, $interest];
    }

    /**
     * Best share of a recent search's words found in the product name or category name.
     *
     * @return array{0: float, 1: string|null}
     */
    private function keywordMatch(array $searches, string $haystack): array
    {
        [$best, $text] = [0.0, null];
        foreach ($searches as $search) {
            $share = count(array_filter($search['tokens'], fn (string $token): bool => str_contains($haystack, $token))) / count($search['tokens']);
            if ($share > $best) {
                [$best, $text] = [$share, $search['text']];
            }
        }

        return [$best, $text];
    }

    /** The strongest personal component explains the result; otherwise a general one. */
    private function reason(array $parts, array $facts): string
    {
        $personal = array_filter(['category' => $parts['category'], 'keyword' => $parts['keyword'], 'shop' => $parts['shop']]);
        if ($personal !== []) {
            return match (array_search(max($personal), $personal, true)) {
                'category' => 'Vì bạn quan tâm '.$facts['category'],
                'keyword' => 'Vì bạn đã tìm “'.$facts['keyword'].'”',
                default => 'Vì bạn quan tâm gian hàng '.$facts['shop'],
            };
        }
        $general = array_filter(['popularity' => $facts['popular'] ? $parts['popularity'] : 0, 'rating' => $facts['rated'] ? $parts['rating'] : 0]);
        if ($general !== []) {
            return array_search(max($general), $general, true) === 'rating' ? 'Được đánh giá cao' : 'Đang được nhiều người quan tâm';
        }

        return $facts['fresh'] ? 'Mới đăng gần đây' : 'Có thể bạn sẽ thích';
    }
}
