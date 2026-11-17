<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Optional extra line for an address (building, floor, room, landmark). The order keeps its own copy,
// like the other shipping fields, so editing the saved address later does not change past orders.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addresses', function (Blueprint $table): void {
            $table->string('address_detail', 255)->nullable()->after('address_line');
        });
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('shipping_address_detail', 255)->nullable()->after('shipping_address_line');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('shipping_address_detail');
        });
        Schema::table('addresses', function (Blueprint $table): void {
            $table->dropColumn('address_detail');
        });
    }
};
