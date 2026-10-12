<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reporter_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('target_type', 20);
            $table->unsignedBigInteger('target_id');
            $table->string('target_name', 255);
            $table->string('reason', 30);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('PENDING');
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('handled_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
            $table->unsignedTinyInteger('open_slot')->nullable()->virtualAs("case when status in ('PENDING', 'IN_REVIEW') then 1 else null end");
            $table->unique(['reporter_user_id', 'target_type', 'target_id', 'open_slot'], 'reports_one_open_per_reporter');
            $table->index(['target_type', 'target_id']);
            $table->index(['status', 'created_at', 'id']);
            $table->index(['reporter_user_id', 'created_at', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};
