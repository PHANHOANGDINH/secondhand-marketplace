<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete()->restrictOnUpdate();
            $table->string('image_path', 512);
            $table->string('alt_text', 255)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->index(['product_id', 'sort_order', 'id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
