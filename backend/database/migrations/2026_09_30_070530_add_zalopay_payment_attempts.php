<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['orders' => 'payment_method', 'payments' => 'method'] as $table => $field) {
            DB::statement("ALTER TABLE `$table` DROP CHECK `{$table}_{$field}_enum_check`, ADD CONSTRAINT `{$table}_{$field}_enum_check` CHECK (`$field` IN ('COD','VNPAY','ZALOPAY'))");
        }
        Schema::table('payments', function (Blueprint $table): void {
            $table->uuid('request_key')->nullable();
            $table->unique(['order_id', 'request_key']);
            $table->text('order_url')->nullable();
            $table->json('preferred_payment_method')->nullable();
            $table->unsignedInteger('gateway_app_id')->nullable();
            $table->unsignedBigInteger('gateway_app_time')->nullable();
            $table->dateTime('gateway_requested_at', 6)->nullable();
            $table->dateTime('expires_at', 6)->nullable();
            $table->boolean('reconciliation_required')->default(false);
        });
    }

    public function down(): void
    {
        if (DB::table('payments')->where('method', 'ZALOPAY')->exists() || DB::table('orders')->where('payment_method', 'ZALOPAY')->exists()) {
            throw new RuntimeException('Cannot remove ZaloPay schema while payment history exists.');
        }
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropUnique(['order_id', 'request_key']);
            $table->dropColumn(['request_key', 'order_url', 'preferred_payment_method', 'gateway_app_id', 'gateway_app_time', 'gateway_requested_at', 'expires_at', 'reconciliation_required']);
        });
        foreach (['orders' => 'payment_method', 'payments' => 'method'] as $table => $field) {
            DB::statement("ALTER TABLE `$table` DROP CHECK `{$table}_{$field}_enum_check`, ADD CONSTRAINT `{$table}_{$field}_enum_check` CHECK (`$field` IN ('COD','VNPAY'))");
        }
    }
};
