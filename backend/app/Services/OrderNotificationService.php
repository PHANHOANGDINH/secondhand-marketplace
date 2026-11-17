<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Enums\ShopOrderStatus as Status;
use App\Events\NotificationCreated;
use App\Http\Resources\NotificationResource;
use App\Models\Order;
use App\Models\Shop;
use App\Models\ShopOrder;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * In-app reminders about orders: placed, paid online, and every status change, sent to the other party.
 * Rows join the caller's transaction (no extra locks); the broadcast is dispatched only after it commits.
 */
class OrderNotificationService
{
    /** Types listed under the "Đơn hàng" filter. */
    public const TYPES = ['ORDER_PLACED', 'ORDER_PAID', 'ORDER_STATUS_CHANGED', 'AUCTION_ORDER_CREATED'];

    public function placed(Order $order): void
    {
        $code = $order->order_code;
        if ($order->payment_method === PaymentMethod::ZALOPAY) {
            // The seller cannot act before the money is verified, so they hear about it in paid().
            $this->persist($order->buyer_id, 'ORDER_PLACED', 'order:'.$order->id, 'Đơn hàng '.$code.' đã được tạo. Hãy hoàn tất thanh toán ZaloPay để người bán xử lý đơn.', '/orders/'.$order->id, $this->extra($order));

            return;
        }
        $this->persist($order->buyer_id, 'ORDER_PLACED', 'order:'.$order->id, 'Đặt hàng thành công! Đơn '.$code.' đang chờ người bán xác nhận.', '/orders/'.$order->id, $this->extra($order));
        $this->sellers($order, 'ORDER_PLACED', 'order:'.$order->id, 'Bạn có đơn hàng mới '.$code.'. Hãy xác nhận đơn sớm nhé.');
    }

    public function paid(Order $order): void
    {
        $code = $order->order_code;
        $this->persist($order->buyer_id, 'ORDER_PAID', 'order:'.$order->id, 'Thanh toán ZaloPay cho đơn '.$code.' thành công. Đơn đang chờ người bán xác nhận.', '/orders/'.$order->id, $this->extra($order));
        $this->sellers($order, 'ORDER_PAID', 'order:'.$order->id, 'Đơn hàng '.$code.' đã được thanh toán qua ZaloPay. Hãy xác nhận đơn sớm nhé.');
    }

    /** Expects the part with its order and shop loaded; a buyer's cancellation goes to the seller, everything else to the buyer. */
    public function statusChanged(ShopOrder $part, Status $status, bool $byBuyer): void
    {
        $order = $part->order;
        $code = $order->order_code;
        $shop = $part->shop->name;
        $reason = $part->cancellation_reason === null ? '' : ' Lý do: '.Str::limit(trim($part->cancellation_reason), 120);
        $key = 'shop_order:'.$part->id.':'.$status->value;
        $extra = [...$this->extra($order), 'shop_order_id' => (string) $part->id, 'order_status' => $status->value];
        if ($byBuyer) {
            $this->persist($part->shop->owner_id, 'ORDER_STATUS_CHANGED', $key, 'Người mua đã hủy đơn '.$code.'.'.$reason, '/seller/orders/'.$part->id, $extra);

            return;
        }
        $message = match ($status) {
            Status::CONFIRMED => $shop.' đã xác nhận đơn '.$code.'.',
            Status::PROCESSING => $shop.' đang chuẩn bị hàng cho đơn '.$code.'.',
            Status::SHIPPING => 'Đơn '.$code.' từ '.$shop.' đang được giao đến bạn.',
            Status::COMPLETED => 'Đơn '.$code.' từ '.$shop.' đã giao thành công. Hãy đánh giá sản phẩm nhé!',
            Status::CANCELLED => $shop.' đã hủy đơn '.$code.'.'.$reason,
            Status::PENDING => null,
        };
        if ($message !== null) {
            $this->persist($order->buyer_id, 'ORDER_STATUS_CHANGED', $key, $message, '/orders/'.$order->id, $extra);
        }
    }

    private function sellers(Order $order, string $type, string $eventKey, string $message): void
    {
        $parts = $order->shopOrders()->orderBy('id')->get(['id', 'shop_id']);
        $owners = Shop::withTrashed()->whereIn('id', $parts->pluck('shop_id'))->pluck('owner_id', 'id');
        foreach ($parts as $part) {
            $this->persist($owners[$part->shop_id] ?? null, $type, $eventKey.':shop_order:'.$part->id, $message, '/seller/orders/'.$part->id, [...$this->extra($order), 'shop_order_id' => (string) $part->id]);
        }
    }

    private function extra(Order $order): array
    {
        return ['order_id' => (string) $order->id, 'order_code' => $order->order_code];
    }

    private function persist(?int $recipientId, string $type, string $eventKey, string $message, string $target, array $extra): void
    {
        $user = $recipientId === null ? null : User::find($recipientId);
        if (! $user) {
            return;
        }
        $data = ['type' => $type, 'message' => $message, 'target_route' => $target, ...$extra];
        $row = $user->notifications()->firstOrCreate(['dedup_key' => hash('sha256', $recipientId.'|'.$type.'|'.$eventKey)], ['id' => (string) Str::uuid(), 'type' => $type, 'data' => $data]);
        if ($row->wasRecentlyCreated) {
            event(new NotificationCreated($recipientId, (new NotificationResource($row))->resolve()));
        }
    }
}
