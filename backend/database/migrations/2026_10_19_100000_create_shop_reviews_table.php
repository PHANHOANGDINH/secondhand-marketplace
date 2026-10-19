<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Star ratings for a whole shop: one per buyer with a completed order there; editable. */
    public function up(): void
    {
        Schema::create('shop_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete()->restrictOnUpdate();
            $table->unsignedTinyInteger('rating');
            $table->text('comment')->nullable();
            $table->string('status', 16)->default('PUBLISHED');
            $table->string('moderation_reason', 1000)->nullable();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
            $table->unique(['shop_id', 'user_id']);
            $table->index(['shop_id', 'status', 'id']);
        });
        DB::statement("ALTER TABLE `shop_reviews` ADD CONSTRAINT `shop_reviews_status_check` CHECK (`status` IN ('PUBLISHED', 'HIDDEN'))");
        DB::statement('ALTER TABLE `shop_reviews` ADD CONSTRAINT `shop_reviews_rating_check` CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_reviews');
    }
};
