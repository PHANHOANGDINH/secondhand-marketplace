<?php

use App\Models\Auction;
use App\Models\User;
use App\Services\AuctionCheckoutService;
use App\Services\AuctionNotificationService;
use App\Services\AuctionService;
use App\Services\CheckoutService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
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
if (isset($data['at'])) {
    Carbon::setTestNow($data['at']);
    CarbonImmutable::setTestNow($data['at']);
}
try {
    $service = app(AuctionService::class);
    if ($data['action'] === 'finalize') {
        $ended = $service->finalize(Auction::findOrFail($data['auction']));
        echo json_encode(['status' => 200, 'winner' => $ended->winning_bid_id]);
    } else {
        $user = User::findOrFail($data['user']);
        if ($data['action'] === 'remind') {
            $created = app(AuctionNotificationService::class)->remind(Auction::findOrFail($data['auction']));
            echo json_encode(['status' => 200, 'created' => $created]);
        } elseif ($data['action'] === 'start') {
            $service->start(Auction::findOrFail($data['auction']));
            echo json_encode(['status' => 200]);
        } elseif ($data['action'] === 'winner-checkout') {
            $result = app(AuctionCheckoutService::class)->checkout($user, Auction::findOrFail($data['auction']), $data['address'], $data['key']);
            echo json_encode(['status' => $result['created'] ? 201 : 200, 'order' => $result['order']->id]);
        } elseif ($data['action'] === 'fixed-checkout') {
            $result = app(CheckoutService::class)->checkout($user, $data['address'], $data['key']);
            echo json_encode(['status' => $result['created'] ? 201 : 200, 'order' => $result['order']->id]);
        } elseif ($data['action'] === 'create') {
            $auction = $service->create($user, $data['data']);
            echo json_encode(['status' => 201, 'auction' => $auction->id]);
        } elseif ($data['action'] === 'admin-cancel') {
            $service->cancelByAdmin($user, Auction::findOrFail($data['auction']), ['reason' => $data['reason'], 'expected_status' => $data['expected_status']]);
            echo json_encode(['status' => 200]);
        } elseif ($data['action'] === 'cancel') {
            $service->cancel($user, Auction::findOrFail($data['auction']));
            echo json_encode(['status' => 200]);
        } else {
            $bid = $service->placeBid($user, Auction::findOrFail($data['auction']), $data['amount']);
            echo json_encode(['status' => 201, 'bid' => $bid->id]);
        }
    }
} catch (HttpExceptionInterface $e) {
    echo json_encode(['status' => $e->getStatusCode()]);
} catch (ValidationException) {
    echo json_encode(['status' => 422]);
} catch (AuthorizationException) {
    echo json_encode(['status' => 403]);
} catch (ModelNotFoundException) {
    echo json_encode(['status' => 404]);
}
