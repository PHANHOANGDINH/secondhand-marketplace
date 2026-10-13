<?php
// E2E preparation on the TEST database copy only: give the demo accounts a throwaway password.
require __DIR__.'/../../backend/vendor/autoload.php';
$app = require __DIR__.'/../../backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if (! app()->environment('testing') || DB::selectOne('SELECT DATABASE() name')->name !== 'secondhand_marketplace_test') { fwrite(STDERR, "Refusing.\n"); exit(1); }
$secret = json_decode(file_get_contents(__DIR__.'/secret.json'), true)['password'];
$n = DB::table('users')->where('email', 'like', 'c1-%@example.test')->update(['password' => password_hash($secret, PASSWORD_BCRYPT)]);
$out = ['users' => DB::table('users')->where('email', 'like', 'c1-%@example.test')->pluck('id', 'email')->all()];
$out['live'] = DB::table('auctions')->where('status', 'LIVE')->where('ends_at', '>', now())->orderBy('id')->get(['id', 'product_id', 'current_price', 'bid_increment', 'seller_id'])->all();
$out['scheduled'] = DB::table('auctions')->where('status', 'SCHEDULED')->pluck('id')->all();
$out['videos'] = DB::table('product_videos')->where('status', 'READY')->pluck('id')->all();
$out['products'] = DB::table('products')->where('name', 'like', 'C1 Demo%')->where('status', 'ACTIVE')->where('stock_quantity', '>', 0)->orderBy('id')->get(['id', 'name', 'slug', 'price', 'shop_id', 'stock_quantity'])->all();
$out['shops'] = DB::table('shops')->where('name', 'like', 'C1 Demo%')->get(['id', 'name', 'slug', 'owner_id'])->all();
$out['payments'] = DB::table('payments')->where('reconciliation_required', 1)->whereNull('reconciliation_resolved_at')->pluck('id')->all();
file_put_contents(__DIR__.'/data.json', json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "accounts updated: $n; live auctions: ".count($out['live'])."; products: ".count($out['products'])."\n";
