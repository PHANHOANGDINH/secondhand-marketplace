<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_order_id')->constrained('shop_orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->restrictOnUpdate();
            $table->string('product_name', 200);
            $table->string('product_image', 512);
            $table->decimal('product_price', 15, 2);
            $table->unsignedSmallInteger('quantity');
            $table->decimal('subtotal', 15, 2);
            $table->unique(['shop_order_id', 'product_id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement('ALTER TABLE `order_items` ADD CONSTRAINT `order_items_quantity_check` CHECK (quantity BETWEEN 1 AND 99)');
        DB::statement('ALTER TABLE `order_items` ADD CONSTRAINT `order_items_amounts_check` CHECK (product_price > 0 AND subtotal = product_price * quantity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
