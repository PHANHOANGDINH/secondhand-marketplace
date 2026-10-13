<?php

use App\Enums\PaymentMethod;
use App\Models\Address;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ShopOrder;
use App\Models\User;
use App\Services\AddressService;
use App\Services\CheckoutService;
use App\Services\ReviewService;
use App\Services\ShopOrderService;
use App\Services\ZaloPayService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || DB::connection()->getDatabaseName() !== 'secondhand_marketplace_test' || DB::selectOne('SELECT DATABASE() name')->name !== 'secondhand_marketplace_test') {
    throw new RuntimeException('Unsafe worker database.');
}
$data = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
file_put_contents($data['ready'], 'ready');
$deadline = microtime(true) + 15;
while (! is_file($data['go'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timeout');
    } usleep(10000);
}
try {
    $user = User::findOrFail($data['user']);
    if ($data['action'] === 'checkout') {
        $result = app(CheckoutService::class)->checkout($user, $data['address'], $data['key'], PaymentMethod::COD, [], [], $data['vouchers'] ?? []);
        echo json_encode(['status' => $result['created'] ? 201 : 200, 'order' => $result['order']->id]);
    } elseif ($data['action'] === 'zalopay_callback') {
        config(['zalopay.key2' => $data['key2']]);
        $code = app(ZaloPayService::class)->callback($data['callback']);
        echo json_encode(['status' => $code]);
    } elseif ($data['action'] === 'zalopay_retry') {
        config(['zalopay.app_id' => '123456', 'zalopay.key1' => $data['key2'], 'zalopay.key2' => $data['key2'], 'zalopay.expire_seconds' => 900]);
        Http::preventStrayRequests();
        Http::fake(['*/v2/query' => Http::response(['return_code' => 2, 'sub_return_code' => -101, 'is_processing' => false]), '*/v2/create' => Http::response(['return_code' => 1, 'order_url' => 'https://sbgateway.zalopay.vn/pay'])]);
        $payment = app(ZaloPayService::class)->retry($user, Order::findOrFail($data['order']), $data['key'], []);
        echo json_encode(['status' => 200, 'payment' => $payment->id]);
    } elseif ($data['action'] === 'address_create') {
        $address = app(AddressService::class)->create($user, $data['address_data']);
        echo json_encode(['status' => 200, 'address' => $address->id]);
    } elseif ($data['action'] === 'address_default') {
        app(AddressService::class)->setDefault($user, Address::findOrFail($data['address']));
        echo json_encode(['status' => 200]);
    } elseif ($data['action'] === 'address_delete') {
        app(AddressService::class)->delete($user, Address::findOrFail($data['address']));
        echo json_encode(['status' => 200]);
    } elseif ($data['action'] === 'review_create') {
        $review = app(ReviewService::class)->create($user, OrderItem::findOrFail($data['item']), ['rating' => 5, 'comment' => 'Concurrent verified purchase']);
        echo json_encode(['status' => 200, 'review' => $review->id]);
    } else {
        app(ShopOrderService::class)->transition($user, ShopOrder::findOrFail($data['part']), $data['transition'], $data['action'] === 'cancel');
        echo json_encode(['status' => 200]);
    }
} catch (ValidationException) {
    echo json_encode(['status' => 422]);
} catch (HttpExceptionInterface $e) {
    echo json_encode(['status' => $e->getStatusCode()]);
} catch (ModelNotFoundException) {
    echo json_encode(['status' => 404]);
} catch (AuthorizationException) {
    echo json_encode(['status' => 403]);
}
