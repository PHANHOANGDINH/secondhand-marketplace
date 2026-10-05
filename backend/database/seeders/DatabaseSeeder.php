<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing']) || ! in_array(DB::connection()->getDatabaseName(), ['secondhand_marketplace', 'secondhand_marketplace_test'], true)) {
            throw new RuntimeException('Demo seed is only allowed in the project local/test databases.');
        }
        DB::transaction(function (): void {
            $this->call([CategorySeeder::class, DemoUserSeeder::class, DemoMarketplaceSeeder::class]);
        });
    }
}
