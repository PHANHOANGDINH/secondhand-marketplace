<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete()->restrictOnUpdate();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->restrictOnUpdate();
            $table->unsignedSmallInteger('quantity');
            $table->unique(['cart_id', 'product_id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement('ALTER TABLE `cart_items` ADD CONSTRAINT `cart_items_quantity_check` CHECK (quantity BETWEEN 1 AND 99)');
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_items');
    }
};
