<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shops', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_id')->unique()->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->string('name', 150);
            $table->string('slug', 180)->unique();
            $table->text('description')->nullable();
            $table->string('logo_path', 512)->nullable();
            $table->string('status', 16)->default('PENDING');
            $table->string('moderation_reason', 1000)->nullable();
            $table->softDeletes('deleted_at', 6);
            $table->index(['status', 'created_at', 'id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement("ALTER TABLE `shops` ADD CONSTRAINT `shops_status_enum_check` CHECK (`status` IN ('PENDING', 'ACTIVE', 'SUSPENDED', 'REJECTED'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};
