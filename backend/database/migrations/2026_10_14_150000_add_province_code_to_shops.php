<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            // Code from config/provinces.php; null = the shop has not chosen an area yet.
            $table->string('province_code', 2)->nullable()->after('description');
            $table->index(['province_code', 'status'], 'shops_province_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropIndex('shops_province_status_index');
            $table->dropColumn('province_code');
        });
    }
};
