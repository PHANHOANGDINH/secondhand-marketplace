<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete()->restrictOnUpdate();
            $table->string('status', 16)->default('PENDING');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('shipping_fee', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->dateTime('confirmed_at', 6)->nullable();
            $table->dateTime('processing_at', 6)->nullable();
            $table->dateTime('shipping_at', 6)->nullable();
            $table->dateTime('completed_at', 6)->nullable();
            $table->dateTime('cancelled_at', 6)->nullable();
            $table->string('cancellation_reason', 1000)->nullable();
            $table->dateTime('stock_restored_at', 6)->nullable();
            $table->unique(['order_id', 'shop_id']);
            $table->index(['shop_id', 'status', 'created_at', 'id']);
            $table->index(['shop_id', 'status', 'completed_at']);
            $table->index(['status', 'created_at', 'id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement("ALTER TABLE `shop_orders` ADD CONSTRAINT `shop_orders_status_enum_check` CHECK (`status` IN ('PENDING', 'CONFIRMED', 'PROCESSING', 'SHIPPING', 'COMPLETED', 'CANCELLED'))");
        DB::statement('ALTER TABLE `shop_orders` ADD CONSTRAINT `shop_orders_amounts_check` CHECK (subtotal >= 0 AND shipping_fee >= 0 AND discount_amount >= 0 AND total_amount >= 0 AND total_amount = subtotal + shipping_fee - discount_amount)');
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_orders');
    }
};
