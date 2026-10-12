<?php

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', fn (User $user, string $userId): bool => (string) $user->id === $userId && $user->status === UserStatus::ACTIVE && ! $user->trashed(), ['guards' => ['web']]);
