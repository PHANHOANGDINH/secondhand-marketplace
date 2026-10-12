<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auctions', function (Blueprint $table): void {
            $table->timestamp('winner_checkout_reminded_at', 6)->nullable();
            $table->index(['status', 'winner_order_id', 'winner_checkout_reminded_at', 'winner_checkout_expires_at'], 'auctions_due_reminder_index');
        });
    }

    public function down(): void
    {
        Schema::table('auctions', function (Blueprint $table): void {
            $table->dropIndex('auctions_due_reminder_index');
            $table->dropColumn('winner_checkout_reminded_at');
        });
    }
};
