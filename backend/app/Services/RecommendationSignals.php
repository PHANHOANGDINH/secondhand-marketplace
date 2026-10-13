<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SearchLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Writes the behaviour signals used by recommendations. Signed-in users only; never an IP address. */
class RecommendationSignals
{
    public function recordView(?User $user, Product $product): void
    {
        if (! $user || (int) $product->shop?->owner_id === (int) $user->id) {
            return;
        }
        $now = now();
        // One statement, so the "once per window" check and the insert cannot be split by another request.
        DB::insert(
            'INSERT INTO product_views (user_id, product_id, viewed_at) SELECT ?, ?, ? FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM product_views WHERE user_id = ? AND product_id = ? AND viewed_at > ?)',
            [$user->id, $product->id, $now->format('Y-m-d H:i:s.u'), $user->id, $product->id, $now->copy()->subMinutes((int) config('recommendations.view_dedupe_minutes'))->format('Y-m-d H:i:s.u')],
        );
    }

    public function recordSearch(?User $user, ?string $query): void
    {
        $query = mb_substr(trim((string) $query), 0, 100);
        if (! $user || $query === '') {
            return;
        }
        SearchLog::create(['user_id' => $user->id, 'query' => $query]);
    }
}
