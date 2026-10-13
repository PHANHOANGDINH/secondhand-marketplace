<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Signed-in users only; no IP address or user agent is stored.
        Schema::create('product_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->dateTime('viewed_at', 6);
            $table->index(['user_id', 'product_id', 'viewed_at']);
            $table->index(['user_id', 'viewed_at']);
        });
        Schema::create('search_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('query', 100);
            $table->dateTime('created_at', 6);
            $table->index(['user_id', 'created_at', 'id']);
        });
        // Popularity counts wishlists and order lines inside a time window.
        Schema::table('wishlists', function (Blueprint $table): void {
            $table->index(['product_id', 'created_at'], 'wishlists_product_created_index');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->index(['product_id', 'created_at'], 'order_items_product_created_index');
        });
    }

    public function down(): void
    {
        // MySQL let the new composite indexes take over from the implicit foreign-key indexes,
        // so those are put back before the composite ones can be dropped.
        Schema::table('order_items', function (Blueprint $table): void {
            $table->index('product_id', 'order_items_product_id_foreign');
        });
        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('order_items_product_created_index');
        });
        Schema::table('wishlists', function (Blueprint $table): void {
            $table->index('product_id', 'wishlists_product_id_foreign');
        });
        Schema::table('wishlists', function (Blueprint $table): void {
            $table->dropIndex('wishlists_product_created_index');
        });
        Schema::dropIfExists('search_logs');
        Schema::dropIfExists('product_views');
    }
};
