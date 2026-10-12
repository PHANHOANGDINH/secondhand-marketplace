<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_video_id')->constrained('product_videos')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->string('status', 16)->default('PUBLISHED');
            $table->timestamps();
            $table->index(['product_video_id', 'created_at']);
            $table->index(['product_video_id', 'status', 'id']);
        });
        DB::statement("ALTER TABLE video_comments ADD CONSTRAINT video_comments_status_check CHECK (status IN ('PUBLISHED','HIDDEN'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('video_comments');
    }
};
