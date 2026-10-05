<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('order_code', 32)->unique();
            $table->char('checkout_key', 36);
            $table->char('request_hash', 64);
            $table->string('shipping_recipient_name', 100);
            $table->string('shipping_phone', 20);
            $table->string('shipping_address_line', 255);
            $table->string('shipping_ward', 100);
            $table->string('shipping_district', 100)->nullable();
            $table->string('shipping_province', 100);
            $table->char('shipping_country_code', 2)->default('VN');
            $table->decimal('subtotal', 15, 2);
            $table->decimal('shipping_fee', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2);
            $table->char('currency', 3)->default('VND');
            $table->string('payment_method', 16)->default('COD');
            $table->unique(['buyer_id', 'checkout_key']);
            $table->index(['buyer_id', 'created_at', 'id']);
            $table->index(['created_at', 'id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement("ALTER TABLE `orders` ADD CONSTRAINT `orders_payment_method_enum_check` CHECK (`payment_method` IN ('COD', 'VNPAY'))");
        DB::statement('ALTER TABLE `orders` ADD CONSTRAINT `orders_amounts_check` CHECK (subtotal >= 0 AND shipping_fee >= 0 AND discount_amount >= 0 AND total_amount >= 0 AND total_amount = subtotal + shipping_fee - discount_amount)');
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
