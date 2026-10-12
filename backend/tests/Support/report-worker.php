<?php

use App\Models\Report;
use App\Models\User;
use App\Services\ReportService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! app()->environment('testing') || DB::connection()->getDatabaseName() !== 'secondhand_marketplace_test') {
    throw new RuntimeException('Only isolated test DB allowed');
}
config(['broadcasting.default' => 'null']);
$job = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
file_put_contents($job['ready'], 'ready');
$deadline = microtime(true) + 25;
while (! is_file($job['go'])) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Barrier timeout');
    } usleep(10000);
}
try {
    $service = app(ReportService::class);
    $user = User::findOrFail($job['user']);
    if ($job['action'] === 'create') {
        $service->create($user, $job['data']);
        $status = 201;
    } else {
        $service->moderate($user, Report::findOrFail($job['report']), $job['data']);
        $status = 200;
    }
    echo json_encode(['status' => $status]);
} catch (HttpExceptionInterface $e) {
    echo json_encode(['status' => $e->getStatusCode()]);
}
