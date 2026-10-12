<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Additive only: records how an Admin settled a payment flagged for reconciliation. */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dateTime('reconciliation_resolved_at', 6)->nullable();
            $table->foreignId('reconciliation_resolved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('reconciliation_note', 1000)->nullable();
            $table->index(['reconciliation_required', 'reconciliation_resolved_at', 'id'], 'payments_reconciliation_queue_index');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropForeign(['reconciliation_resolved_by']);
            $table->dropIndex('payments_reconciliation_queue_index');
            $table->dropColumn(['reconciliation_resolved_at', 'reconciliation_resolved_by', 'reconciliation_note']);
        });
    }
};
