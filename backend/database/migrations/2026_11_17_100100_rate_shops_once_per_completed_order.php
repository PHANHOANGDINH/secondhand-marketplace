<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Shop ratings move from "one per buyer and shop" to "one per completed shop order" (approved by the
// product owner on 2026-11-17). Each existing rating is linked to the buyer's newest completed order at
// that shop, so the buyer can still rate their other completed orders.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shop_reviews', function (Blueprint $table): void {
            $table->foreignId('shop_order_id')->nullable()->after('user_id')->unique()->constrained('shop_orders')->restrictOnDelete()->restrictOnUpdate();
        });
        foreach (DB::table('shop_reviews')->orderBy('id')->get(['id', 'shop_id', 'user_id']) as $review) {
            $part = DB::table('shop_orders')
                ->join('orders', 'orders.id', '=', 'shop_orders.order_id')
                ->where('shop_orders.shop_id', $review->shop_id)
                ->where('orders.buyer_id', $review->user_id)
                ->where('shop_orders.status', 'COMPLETED')
                ->whereNotIn('shop_orders.id', DB::table('shop_reviews')->whereNotNull('shop_order_id')->select('shop_order_id'))
                ->orderByDesc('shop_orders.completed_at')->orderByDesc('shop_orders.id')
                ->value('shop_orders.id');
            if ($part !== null) {
                DB::table('shop_reviews')->where('id', $review->id)->update(['shop_order_id' => $part]);
            }
        }
        Schema::table('shop_reviews', function (Blueprint $table): void {
            $table->index(['shop_id', 'user_id']);
            $table->dropUnique(['shop_id', 'user_id']);
        });
    }

    public function down(): void
    {
        $duplicates = DB::table('shop_reviews')->select('shop_id', 'user_id')->groupBy('shop_id', 'user_id')->havingRaw('COUNT(*) > 1')->exists();
        if ($duplicates) {
            throw new RuntimeException('Cannot restore one rating per buyer and shop: some buyers have several ratings for a shop. Decide which to keep before rolling back.');
        }
        Schema::table('shop_reviews', function (Blueprint $table): void {
            $table->unique(['shop_id', 'user_id']);
            $table->dropIndex(['shop_id', 'user_id']);
            $table->dropConstrainedForeignId('shop_order_id');
        });
    }
};
