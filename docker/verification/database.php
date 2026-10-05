<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

require '/var/www/html/vendor/autoload.php';
$app = require '/var/www/html/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! $app->environment('production') || config('database.connections.mysql.host') !== 'mysql' || DB::selectOne('SELECT DATABASE() AS name')->name !== 'marketplace_phase14') {
    throw new RuntimeException('Not the isolated Docker verification database.');
}
if ($input['action'] === 'snapshot') {
    $result = [];
    foreach (['users', 'addresses', 'shops', 'categories', 'products', 'product_images', 'carts', 'cart_items', 'orders', 'shop_orders', 'order_items', 'payments', 'reviews', 'audit_logs', 'personal_access_tokens', 'migrations'] as $table) {
        $rows = DB::table($table)->orderBy('id')->get();
        $result[$table] = ['count' => $rows->count(), 'sha256' => hash('sha256', $rows->toJson())];
    }
    echo json_encode($result);
    exit;
}
$nonce = $input['nonce'];
if (! preg_match('/^[a-f0-9]{32}$/D', $nonce)) {
    throw new RuntimeException('Unexpected nonce.');
}
$emails = array_map(fn ($kind) => 'phase14-docker-'.$kind.'-'.$nonce.'@example.test', ['buyer', 'seller', 'admin']);
$ids = DB::table('users')->whereIn('email', $emails)->pluck('id');
if ($ids->count() < 1 || $ids->count() > 3) {
    throw new RuntimeException('Expected one to three isolated fixture users.');
}
if ($input['action'] === 'promote') {
    User::where('email', $emails[2])->firstOrFail()->forceFill(['role' => 'ADMIN'])->save();
    echo json_encode(['promoted' => true]);
    exit;
}
if ($input['action'] === 'browser_checkpoint') {
    if (DB::table('carts')->whereNotIn('user_id', $ids)->exists()) {
        throw new RuntimeException('Unrelated cart data.');
    }
    $buyerId = DB::table('users')->where('email', $emails[0])->value('id');
    $rows = DB::table('carts')->where('user_id', $buyerId)->orderBy('id')->get();
    echo json_encode(['buyer_cart' => ['count' => $rows->count(), 'sha256' => hash('sha256', $rows->toJson())]]);
    exit;
}
if ($input['action'] !== 'cleanup') {
    throw new RuntimeException('Unknown action.');
}
$paths = [];
DB::transaction(function () use ($ids, $nonce, &$paths) {
    $shopIds = DB::table('shops')->whereIn('owner_id', $ids)->pluck('id');
    $productIds = DB::table('products')->whereIn('shop_id', $shopIds)->pluck('id');
    $orderIds = DB::table('orders')->whereIn('buyer_id', $ids)->pluck('id');
    $partIds = DB::table('shop_orders')->whereIn('order_id', $orderIds)->pluck('id');
    $categoryIds = DB::table('categories')->where('name', 'Phase14 '.$nonce)->pluck('id');
    $cartIds = DB::table('carts')->whereIn('user_id', $ids)->pluck('id');
    if (DB::table('shop_orders')->whereIn('shop_id', $shopIds)->whereNotIn('order_id', $orderIds)->exists() || DB::table('shop_orders')->whereIn('id', $partIds)->whereNotIn('shop_id', $shopIds)->exists() || DB::table('order_items')->whereIn('product_id', $productIds)->whereNotIn('shop_order_id', $partIds)->exists() || DB::table('order_items')->whereIn('shop_order_id', $partIds)->whereNotIn('product_id', $productIds)->exists() || DB::table('products')->whereIn('category_id', $categoryIds)->whereNotIn('id', $productIds)->exists() || DB::table('cart_items')->whereIn('product_id', $productIds)->whereNotIn('cart_id', $cartIds)->exists()) {
        throw new RuntimeException('Unrelated references prevent cleanup.');
    }
    $paths = DB::table('product_images')->whereIn('product_id', $productIds)->pluck('image_path')->all();
    foreach ($paths as $path) {
        if (! preg_match('~^products/([0-9]+)/[a-f0-9-]+\.(png|jpg|webp)$~D', $path, $match) || ! $productIds->contains((int) $match[1])) {
            throw new RuntimeException('Unexpected upload path.');
        }
    }
    DB::table('reviews')->whereIn('product_id', $productIds)->delete();
    DB::table('payments')->whereIn('order_id', $orderIds)->delete();
    DB::table('order_items')->whereIn('shop_order_id', $partIds)->delete();
    DB::table('shop_orders')->whereIn('id', $partIds)->delete();
    DB::table('orders')->whereIn('id', $orderIds)->delete();
    DB::table('cart_items')->whereIn('cart_id', $cartIds)->delete();
    DB::table('carts')->whereIn('id', $cartIds)->delete();
    DB::table('product_images')->whereIn('product_id', $productIds)->delete();
    DB::table('products')->whereIn('id', $productIds)->delete();
    DB::table('shops')->whereIn('id', $shopIds)->delete();
    DB::table('categories')->whereIn('id', $categoryIds)->delete();
    DB::table('audit_logs')->whereIn('actor_id', $ids)->delete();
    DB::table('addresses')->whereIn('user_id', $ids)->delete();
    DB::table('personal_access_tokens')->where('tokenable_type', User::class)->whereIn('tokenable_id', $ids)->delete();
    DB::table('users')->whereIn('id', $ids)->delete();
});
foreach ($paths as $path) {
    Storage::disk('public')->delete($path);
}
echo json_encode(['cleaned' => true, 'uploads_removed' => count($paths)]);
