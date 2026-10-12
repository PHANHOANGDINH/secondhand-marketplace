<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $admin = $request->is('api/v1/admin/reports*');
        $data = ['id' => (string) $this->id, 'target' => $this->safe_target, 'reason' => $this->reason->value, 'reason_label' => $this->reason->label(), 'description' => $this->description, 'status' => $this->status->value, 'status_label' => $this->status->label(), 'created_at' => $this->created_at->toISOString(), 'handled_at' => $this->handled_at?->toISOString()];
        if ($admin) {
            $data['admin_note'] = $this->admin_note;
            $data['can_moderate'] = $this->target_owner_id === null || (int) $this->target_owner_id !== $request->user()->id;
            $data['reporter'] = $this->reporter ? ['id' => (string) $this->reporter->id, 'name' => $this->reporter->name] : null;
            $data['handler'] = $this->handler ? ['id' => (string) $this->handler->id, 'name' => $this->handler->name] : null;
        }

        return $data;
    }
}
