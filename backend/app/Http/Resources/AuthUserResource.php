<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class AuthUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => (string) $this->id, 'name' => $this->name, 'email' => $this->email,
            'phone' => $this->phone, 'avatar_path' => $this->avatar_path,
            'avatar_url' => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
            'role' => $this->role->value, 'status' => $this->status->value,
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'must_change_password' => (bool) $this->must_change_password,
            'created_at' => $this->created_at?->toISOString()];
    }
}
