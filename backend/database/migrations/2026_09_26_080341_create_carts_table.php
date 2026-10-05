<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('carts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete()->restrictOnUpdate();
            $table->dateTime('created_at', 6);
            $table->dateTime('updated_at', 6);
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('carts');
    }
};
