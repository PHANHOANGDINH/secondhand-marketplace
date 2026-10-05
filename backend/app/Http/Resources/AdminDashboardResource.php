<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [...$this->resource, 'recent_orders' => AdminOrderResource::collection($this->resource['recent_orders'])];
    }
}
