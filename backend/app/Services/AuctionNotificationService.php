<?php

namespace App\Services;

use App\Enums\AuctionStatus;
use App\Events\NotificationCreated;
use App\Http\Resources\NotificationResource;
use App\Models\Auction;
use App\Models\Bid;
use App\Models\Order;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuctionNotificationService
{
    /** Writes join the lifecycle transaction; broadcasts only dispatch after its successful commit. */
    public function persist(int $recipientId, Auction $auction, string $type, string $eventKey, string $message, string $target, array $extra = []): ?DatabaseNotification
    {
        return DB::transaction(function () use ($recipientId, $auction, $type, $eventKey, $message, $target, $extra): ?DatabaseNotification {
            $user = User::find($recipientId);
            if (! $user) {
                return null;
            }
            $data = ['type' => $type, 'auction_id' => (string) $auction->id, 'product_id' => (string) $auction->product_id, 'product_name' => $auction->product->name, 'message' => $message, 'target_route' => $target, ...array_intersect_key($extra, array_flip(['winning_amount', 'checkout_deadline']))];
            $row = $user->notifications()->firstOrCreate(['dedup_key' => hash('sha256', $recipientId.'|'.$type.'|'.$eventKey)], ['id' => (string) Str::uuid(), 'type' => $type, 'data' => $data]);
            if ($row->wasRecentlyCreated) {
                event(new NotificationCreated($recipientId, (new NotificationResource($row))->resolve()));
            }

            return $row;
        });
    }

    public function outbid(Auction $auction, Bid $newBid, Bid $previous): void
    {
        if ($previous->user_id === $newBid->user_id) {
            return;
        }
        $this->persist($previous->user_id, $auction, 'OUTBID', 'bid:'.$newBid->id, 'Có người vừa trả giá cao hơn bạn cho '.$auction->product->name.'.', '/auctions/'.$auction->id);
    }

    public function finalized(Auction $auction): void
    {
        $name = $auction->product->name;
        $extra = ['winning_amount' => $auction->winningBid?->amount, 'checkout_deadline' => $auction->winner_checkout_expires_at?->toISOString()];
        if ($auction->winner_user_id) {
            $this->persist($auction->winner_user_id, $auction, 'AUCTION_WON', 'auction:'.$auction->id, 'Bạn đã thắng phiên đấu giá '.$name.'.', '/auctions/'.$auction->id.'/checkout', $extra);
        }
        $this->persist($auction->seller_id, $auction, 'AUCTION_ENDED', 'auction:'.$auction->id, 'Phiên đấu giá '.$name.' đã kết thúc.'.($auction->winner_user_id ? ' Người thắng có thể hoàn tất mua hàng.' : ' Không có người thắng.'), '/seller/auctions/'.$auction->id, $extra);
        foreach ($auction->bids()->where('user_id', '!=', $auction->winner_user_id ?? 0)->where('user_id', '!=', $auction->seller_id)->distinct()->pluck('user_id') as $id) {
            $this->persist($id, $auction, 'AUCTION_ENDED', 'auction:'.$auction->id, 'Phiên đấu giá '.$name.' đã kết thúc.', '/auctions/'.$auction->id);
        }
    }

    public function cancelled(Auction $auction): void
    {
        // Existing rules allow cancellation only before accepted bids; Seller is the relevant participant.
        $this->persist($auction->seller_id, $auction, 'AUCTION_CANCELLED', 'auction:'.$auction->id, 'Phiên đấu giá '.$auction->product->name.' đã được hủy.', '/seller/auctions/'.$auction->id);
    }

    public function orderCreated(Auction $auction, Order $order): void
    {
        $message = 'Đơn hàng cho phiên đấu giá '.$auction->product->name.' đã được tạo.';
        $this->persist($auction->winner_user_id, $auction, 'AUCTION_ORDER_CREATED', 'auction:'.$auction->id, $message, '/orders/'.$order->id);
        $this->persist($auction->seller_id, $auction, 'AUCTION_ORDER_CREATED', 'auction:'.$auction->id, $message, '/seller/orders/'.$order->shopOrders->sole()->id);
    }

    public function remind(Auction $auction): bool
    {
        return DB::transaction(function () use ($auction): bool {
            $locked = Auction::whereKey($auction->id)->lockForUpdate()->firstOrFail();
            $time = now();
            $minutes = max(0, (int) config('auctions.checkout_reminder_minutes', 60));
            if ($locked->status !== AuctionStatus::ENDED || ! $locked->winner_user_id || $locked->winner_order_id || $locked->winner_checkout_reminded_at || ! $locked->winner_checkout_expires_at || $locked->winner_checkout_expires_at->lessThanOrEqualTo($time) || $locked->winner_checkout_expires_at->greaterThan($time->copy()->addMinutes($minutes))) {
                return false;
            }
            $this->persist($locked->winner_user_id, $locked, 'WINNER_CHECKOUT_EXPIRING', 'auction:'.$locked->id, 'Quyền mua '.$locked->product->name.' sắp hết hạn. Hãy hoàn tất mua hàng trước thời hạn.', '/auctions/'.$locked->id.'/checkout', ['winning_amount' => $locked->winningBid->amount, 'checkout_deadline' => $locked->winner_checkout_expires_at->toISOString()]);
            $locked->forceFill(['winner_checkout_reminded_at' => $time])->save();

            return true;
        }, 3);
    }
}
