<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('scope', 16);
            $table->foreignId('shop_id')->nullable()->constrained('shops')->restrictOnDelete()->restrictOnUpdate();
            $table->string('type', 16);
            $table->decimal('value', 15, 2);
            $table->decimal('min_order', 15, 2)->default(0);
            $table->decimal('max_discount', 15, 2)->nullable();
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('per_user_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->dateTime('starts_at', 6);
            $table->dateTime('ends_at', 6);
            $table->string('status', 16)->default('ACTIVE');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->index(['scope', 'status', 'ends_at', 'id']);
            $table->index(['shop_id', 'status', 'id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement("ALTER TABLE `vouchers` ADD CONSTRAINT `vouchers_scope_enum_check` CHECK (`scope` IN ('PLATFORM', 'SHOP'))");
        DB::statement("ALTER TABLE `vouchers` ADD CONSTRAINT `vouchers_type_enum_check` CHECK (`type` IN ('PERCENT', 'FIXED'))");
        DB::statement("ALTER TABLE `vouchers` ADD CONSTRAINT `vouchers_status_enum_check` CHECK (`status` IN ('ACTIVE', 'INACTIVE'))");
        DB::statement("ALTER TABLE `vouchers` ADD CONSTRAINT `vouchers_scope_shop_check` CHECK ((`scope` = 'SHOP' AND shop_id IS NOT NULL) OR (`scope` = 'PLATFORM' AND shop_id IS NULL))");
        DB::statement("ALTER TABLE `vouchers` ADD CONSTRAINT `vouchers_value_check` CHECK (value > 0 AND min_order >= 0 AND (`type` <> 'PERCENT' OR value <= 100) AND (max_discount IS NULL OR (`type` = 'PERCENT' AND max_discount > 0)))");
        DB::statement('ALTER TABLE `vouchers` ADD CONSTRAINT `vouchers_period_check` CHECK (ends_at > starts_at)');
        DB::statement('ALTER TABLE `vouchers` ADD CONSTRAINT `vouchers_limits_check` CHECK ((usage_limit IS NULL OR usage_limit >= 1) AND (per_user_limit IS NULL OR per_user_limit >= 1))');

        Schema::create('voucher_redemptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete()->restrictOnUpdate();
            // Null for a PLATFORM voucher, which belongs to the whole order.
            $table->foreignId('shop_order_id')->nullable()->constrained('shop_orders')->restrictOnDelete()->restrictOnUpdate();
            $table->decimal('discount_amount', 15, 2);
            $table->dateTime('released_at', 6)->nullable();
            $table->index(['voucher_id', 'user_id', 'released_at']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement('ALTER TABLE `voucher_redemptions` ADD CONSTRAINT `voucher_redemptions_amount_check` CHECK (discount_amount >= 0)');

        // discount_amount (already there, part of the totals CHECK) stays the sum of the two new columns.
        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->decimal('discount_shop', 15, 2)->default(0)->after('discount_amount');
            $table->decimal('discount_platform', 15, 2)->default(0)->after('discount_shop');
            $table->string('voucher_code', 20)->nullable()->after('discount_platform');
        });
        DB::statement('ALTER TABLE `shop_orders` ADD CONSTRAINT `shop_orders_discount_split_check` CHECK (discount_shop >= 0 AND discount_platform >= 0 AND discount_amount = discount_shop + discount_platform)');
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('platform_voucher_code', 20)->nullable()->after('discount_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('platform_voucher_code');
        });
        DB::statement('ALTER TABLE `shop_orders` DROP CHECK `shop_orders_discount_split_check`');
        Schema::table('shop_orders', function (Blueprint $table): void {
            $table->dropColumn(['discount_shop', 'discount_platform', 'voucher_code']);
        });
        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('vouchers');
    }
};
