<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->restrictOnUpdate();
            $table->string('recipient_name', 100);
            $table->string('phone', 20);
            $table->string('address_line', 255);
            $table->string('ward', 100);
            $table->string('district', 100)->nullable();
            $table->string('province', 100);
            $table->char('country_code', 2)->default('VN');
            $table->boolean('is_default')->default(false);
            $table->index(['user_id', 'is_default', 'id']);
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
