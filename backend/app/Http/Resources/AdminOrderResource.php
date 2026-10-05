<?php

namespace App\Http\Resources;

use App\Models\ShopOrder;
use App\Services\OrderSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'order_code' => $this->order_code, 'buyer' => ['id' => (string) $this->buyer_id, 'name' => $this->buyer && ! $this->buyer->trashed() ? $this->buyer->name : 'Người mua'],
            'subtotal' => $this->subtotal, 'shipping_fee' => $this->shipping_fee, 'discount_amount' => $this->discount_amount, 'total_amount' => $this->total_amount, 'currency' => $this->currency, 'overall_status' => OrderSummary::overallStatus($this->resource), 'created_at' => $this->created_at?->toISOString(),
            'shop_orders' => $this->shopOrders->map(function (ShopOrder $part): array {
                $row = ['id' => (string) $part->id, 'status' => $part->status->value, 'shop' => ['id' => (string) $part->shop_id, 'name' => $part->shop->name, 'slug' => $part->shop->slug, 'archived' => $part->shop->trashed()],
                    'subtotal' => $part->subtotal, 'shipping_fee' => $part->shipping_fee, 'discount_amount' => $part->discount_amount, 'total_amount' => $part->total_amount, 'completed_at' => $part->completed_at?->toISOString(), 'cancelled_at' => $part->cancelled_at?->toISOString()];
                if ($part->relationLoaded('orderItems')) {
                    $row['items'] = OrderItemResource::collection($part->orderItems);
                }

                return $row;
            })->all()];
    }
}
