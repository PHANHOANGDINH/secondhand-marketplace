<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'recipient_name' => $this->recipient_name, 'phone' => $this->phone, 'address_line' => $this->address_line, 'ward' => $this->ward, 'district' => $this->district, 'province' => $this->province, 'country_code' => $this->country_code, 'is_default' => $this->is_default];
    }
}
