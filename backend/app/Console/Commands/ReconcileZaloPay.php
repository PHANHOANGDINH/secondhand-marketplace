<?php

namespace App\Console\Commands;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\ZaloPayService;
use Illuminate\Console\Command;

class ReconcileZaloPay extends Command
{
    protected $signature = 'zalopay:reconcile {--limit=100 : Maximum attempts to query}';

    protected $description = 'Query unresolved ZaloPay attempts without changing inventory or logging gateway payloads';

    public function handle(ZaloPayService $service): int
    {
        $count = 0;
        $rows = Payment::where('method', PaymentMethod::ZALOPAY)->whereIn('status', [PaymentStatus::PENDING, PaymentStatus::FAILED, PaymentStatus::CANCELLED])->where('reconciliation_required', false)->whereNotNull('gateway_requested_at')->where('expires_at', '>=', now()->subDays(7))->oldest('updated_at')->limit(max(1, min(500, (int) $this->option('limit'))))->get();
        foreach ($rows as $row) {
            $service->sync($row);
            $row->touch();
            $count++;
        }
        $this->info("Queried $count payment attempts.");

        return self::SUCCESS;
    }
}
