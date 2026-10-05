<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Str;

class AuditService
{
    public function record(User $actor, string $entity, int $id, string $action, ?string $from, ?string $to, ?string $reason): void
    {
        AuditLog::create(['actor_id' => $actor->id, 'entity_type' => $entity, 'entity_id' => $id, 'action' => $action,
            'from_status' => $from, 'to_status' => $to, 'reason' => $reason, 'request_id' => (string) Str::uuid(), 'created_at' => now()]);
    }
}
