<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 200)->nullable();
            $table->text('description')->nullable();
            $table->string('path')->unique();
            $table->string('mime_type', 32);
            $table->unsignedBigInteger('file_size');
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('poster_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_primary')->default(false);
            $table->string('status', 16)->default('READY');
            $table->unsignedBigInteger('primary_product_id')->nullable()->storedAs("CASE WHEN status = 'READY' AND is_primary = 1 THEN product_id ELSE NULL END")->unique();
            $table->timestamps();
            $table->index(['product_id', 'status', 'sort_order', 'id']);
        });
        DB::statement("ALTER TABLE product_videos ADD CONSTRAINT product_videos_status_check CHECK (status IN ('PROCESSING','READY','HIDDEN','FAILED'))");
        DB::statement("ALTER TABLE product_videos ADD CONSTRAINT product_videos_primary_check CHECK (is_primary = 0 OR status = 'READY')");
    }

    public function down(): void
    {
        Schema::dropIfExists('product_videos');
    }
};
