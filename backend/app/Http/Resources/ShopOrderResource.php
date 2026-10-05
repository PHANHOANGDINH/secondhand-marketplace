<?php

namespace App\Http\Resources;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ShopOrderStatus;
use App\Enums\ShopStatus;
use App\Services\OrderSummary;
use App\Services\ReviewService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShopOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $seller = $request->is('api/v1/seller/orders*');
        if (! $seller && $request->user()) {
            foreach ($this->orderItems as $item) {
                if ($item->relationLoaded('review')) {
                    ReviewService::describeItem($item, $request->user(), $this->status === ShopOrderStatus::COMPLETED, $this->shop->owner_id);
                }
            }
        }

        $online = $this->order->payment_method === PaymentMethod::ZALOPAY;
        $paid = $online && $this->order->payments->contains('status', PaymentStatus::PAID);
        $review = $online && $this->order->payments->contains('reconciliation_required', true);

        return ['payment_method' => $this->order->payment_method->value, 'can_cancel_payment' => ! $online || (! $paid && ! $review), 'payment_ready' => ! $online || ($paid && ! $review), 'id' => (string) $this->id, 'order_id' => (string) $this->order_id, 'status' => $this->status->value, 'shop' => ['id' => (string) $this->shop->id, 'name' => $this->shop->name, 'slug' => $this->shop->slug], 'subtotal' => $this->subtotal, 'shipping_fee' => $this->shipping_fee, 'discount_amount' => $this->discount_amount, 'total_amount' => $this->total_amount, 'items' => OrderItemResource::collection($this->orderItems), 'cancellation_reason' => $this->cancellation_reason, 'created_at' => $this->created_at?->toISOString(), 'confirmed_at' => $this->confirmed_at?->toISOString(), 'processing_at' => $this->processing_at?->toISOString(), 'shipping_at' => $this->shipping_at?->toISOString(), 'completed_at' => $this->completed_at?->toISOString(), 'cancelled_at' => $this->cancelled_at?->toISOString(), 'order_code' => $this->when($seller, fn () => $this->order->order_code), 'shipping_address' => $this->when($seller, fn () => OrderSummary::shipping($this->order)), 'can_update' => $this->when($seller, fn () => ! $this->shop->trashed() && $this->shop->status === ShopStatus::ACTIVE)];
    }
}
