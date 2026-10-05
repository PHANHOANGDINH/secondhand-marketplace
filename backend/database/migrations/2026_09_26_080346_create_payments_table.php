<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedInteger('attempt_number');
            $table->string('method', 16)->default('COD');
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3)->default('VND');
            $table->string('status', 16)->default('PENDING');
            $table->string('transaction_reference', 64)->unique();
            $table->string('gateway_transaction_id', 100)->nullable();
            $table->dateTime('paid_at', 6)->nullable();
            $table->dateTime('failed_at', 6)->nullable();
            $table->string('failure_code', 100)->nullable();
            $table->unique(['order_id', 'attempt_number']);
            $table->unique(['method', 'gateway_transaction_id']);
            $table->index(['order_id', 'created_at', 'id']);
            $table->index(['status', 'created_at', 'id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement("ALTER TABLE `payments` ADD CONSTRAINT `payments_method_enum_check` CHECK (`method` IN ('COD', 'VNPAY'))");
        DB::statement("ALTER TABLE `payments` ADD CONSTRAINT `payments_status_enum_check` CHECK (`status` IN ('PENDING', 'PAID', 'FAILED', 'CANCELLED', 'REFUNDED'))");
        DB::statement('ALTER TABLE `payments` ADD CONSTRAINT `payments_amount_check` CHECK (amount > 0)');
        DB::statement('ALTER TABLE `payments` ADD CONSTRAINT `payments_attempt_check` CHECK (attempt_number >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
