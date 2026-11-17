<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One optional buyer video per product review (MP4/WebM on the public disk). Hiding the review hides it too.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->string('video_path', 255)->nullable()->after('comment');
            $table->string('video_mime_type', 32)->nullable()->after('video_path');
            $table->unsignedBigInteger('video_size')->nullable()->after('video_mime_type');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table): void {
            $table->dropColumn(['video_path', 'video_mime_type', 'video_size']);
        });
    }
};
