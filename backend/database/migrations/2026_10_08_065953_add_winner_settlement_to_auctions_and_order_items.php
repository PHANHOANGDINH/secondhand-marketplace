<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table): void {
            $table->timestamp('winner_checkout_expires_at', 6)->nullable()->index();
            $table->foreignId('winner_order_id')->nullable()->unique()->constrained('orders')->restrictOnDelete();
            $table->timestamp('settled_at', 6)->nullable();
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->foreignId('auction_id')->nullable()->unique()->constrained('auctions')->restrictOnDelete();
        });
        // Keep pre-A4 finalization time; do not extend old expired winner claims.
        $hours = max(1, (int) config('auctions.winner_checkout_hours', 24));
        DB::table('auctions')->where('status', 'ENDED')->whereNotNull('winner_user_id')->update(['winner_checkout_expires_at' => DB::raw("DATE_ADD(updated_at, INTERVAL {$hours} HOUR)")]);
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropForeign(['auction_id']);
            $table->dropUnique(['auction_id']);
            $table->dropColumn('auction_id');
        });
        Schema::table('auctions', function (Blueprint $table): void {
            $table->dropForeign(['winner_order_id']);
            $table->dropUnique(['winner_order_id']);
            $table->dropColumn(['winner_checkout_expires_at', 'winner_order_id', 'settled_at']);
        });
    }
};
