<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->restrictOnDelete()->restrictOnUpdate();
            $table->foreignId('category_id')->constrained('categories')->restrictOnDelete()->restrictOnUpdate();
            $table->string('name', 200);
            $table->string('slug', 240)->unique();
            $table->text('description');
            $table->decimal('price', 15, 2);
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->string('condition', 16);
            $table->string('status', 16)->default('DRAFT');
            $table->string('moderation_reason', 1000)->nullable();
            $table->dateTime('published_at', 6)->nullable();
            $table->softDeletes('deleted_at', 6);
            $table->index(['status', 'deleted_at', 'created_at', 'id']);
            $table->index(['category_id', 'status', 'deleted_at', 'created_at', 'id']);
            $table->index(['shop_id', 'status', 'deleted_at', 'created_at', 'id']);
            $table->index(['status', 'deleted_at', 'price', 'id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });
        DB::statement("ALTER TABLE `products` ADD CONSTRAINT `products_condition_enum_check` CHECK (`condition` IN ('LIKE_NEW', 'GOOD', 'FAIR', 'POOR'))");
        DB::statement("ALTER TABLE `products` ADD CONSTRAINT `products_status_enum_check` CHECK (`status` IN ('DRAFT', 'PENDING', 'ACTIVE', 'SOLD', 'HIDDEN', 'REJECTED'))");
        DB::statement('ALTER TABLE `products` ADD CONSTRAINT `products_price_check` CHECK (price > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
