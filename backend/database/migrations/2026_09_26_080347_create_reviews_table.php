<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('product_id')->constrained('products')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('order_item_id')->unique()->constrained('order_items')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 16)->default('PUBLISHED');
            $table->string('moderation_reason', 1000)->nullable();
            $table->index(['product_id', 'status', 'created_at', 'id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement("ALTER TABLE `reviews` ADD CONSTRAINT `reviews_status_enum_check` CHECK (`status` IN ('PUBLISHED', 'HIDDEN'))");
        DB::statement('ALTER TABLE `reviews` ADD CONSTRAINT `reviews_rating_check` CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
