<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ManagedShopResource extends ShopResource
{
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'archived' => $this->trashed(), 'owner_id' => (string) $this->owner_id, 'moderation_reason' => $this->moderation_reason];
    }
}
