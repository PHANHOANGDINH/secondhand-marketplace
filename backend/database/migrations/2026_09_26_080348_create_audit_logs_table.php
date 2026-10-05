<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('entity_type', 40);
            $table->unsignedBigInteger('entity_id');
            $table->string('action', 80);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->string('reason', 1000)->nullable();
            $table->char('request_id', 36)->nullable();
            $table->dateTime('created_at', 6);
            $table->index(['entity_type', 'entity_id', 'created_at', 'id']);
            $table->index(['actor_id', 'created_at']);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
