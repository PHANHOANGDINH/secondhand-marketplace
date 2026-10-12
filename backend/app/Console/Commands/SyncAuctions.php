<?php

namespace App\Console\Commands;

use App\Enums\AuctionStatus;
use App\Models\Auction;
use App\Services\AuctionNotificationService;
use App\Services\AuctionService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class SyncAuctions extends Command
{
    protected $signature = 'auctions:sync {--limit=100 : Maximum due auctions per invocation}';

    protected $description = 'Start due auctions and finalize expired auctions safely';

    public function handle(AuctionService $service): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 1000]]);
        if ($limit === false) {
            $this->error('Limit must be between 1 and 1000.');

            return self::INVALID;
        }
        $processed = 0;
        $deferred = 0;
        $auctions = Auction::whereIn('status', [AuctionStatus::SCHEDULED, AuctionStatus::LIVE])->where(fn (Builder $q): Builder => $q->where('ends_at', '<=', now()->format('Y-m-d H:i:s.u'))->orWhere(fn (Builder $q): Builder => $q->where('status', AuctionStatus::SCHEDULED)->where('starts_at', '<=', now()->format('Y-m-d H:i:s.u'))))->orderBy('ends_at')->orderBy('id')->limit($limit)->get();
        foreach ($auctions as $auction) {
            try {
                if (now()->greaterThanOrEqualTo($auction->ends_at)) {
                    $service->finalize($auction);
                } else {
                    $service->start($auction);
                } $processed++;
            } catch (HttpExceptionInterface $exception) {
                if ($exception->getStatusCode() !== 409) {
                    throw $exception;
                } $deferred++;
            }
        }
        $time = now();
        $reminders = Auction::where('status', AuctionStatus::ENDED)->whereNotNull('winner_user_id')->whereNull('winner_order_id')->whereNull('winner_checkout_reminded_at')->where('winner_checkout_expires_at', '>', $time->format('Y-m-d H:i:s.u'))->where('winner_checkout_expires_at', '<=', $time->copy()->addMinutes(max(0, (int) config('auctions.checkout_reminder_minutes', 60)))->format('Y-m-d H:i:s.u'))->orderBy('winner_checkout_expires_at')->orderBy('id')->limit($limit)->get();
        foreach ($reminders as $auction) {
            app(AuctionNotificationService::class)->remind($auction);
        }
        $this->info("Processed {$processed}; deferred {$deferred} unavailable auctions.");

        return self::SUCCESS;
    }
}
