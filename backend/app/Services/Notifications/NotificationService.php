<?php

namespace App\Services\Notifications;

use App\Models\Notification;
use App\Models\Role;
use App\Models\User;

class NotificationService
{
    public function notifyUser(User|int $user, string $title, string $body, array $data = [], string $type = 'system', string $channel = 'in_app', ?User $actor = null): Notification
    {
        $userId = $user instanceof User ? $user->id : $user;

        return Notification::query()->create([
            'user_id' => $userId,
            'type' => $type,
            'channel' => $channel,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'sent_at' => now(),
            'created_by' => $actor?->id,
        ]);
    }

    public function notifyRole(string $roleSlug, string $title, string $body, array $data = [], string $type = 'system', string $channel = 'in_app', ?User $actor = null): ?Notification
    {
        $role = Role::query()->where('slug', $roleSlug)->first();

        if (! $role) {
            return null;
        }

        return Notification::query()->create([
            'role_id' => $role->id,
            'type' => $type,
            'channel' => $channel,
            'title' => $title,
            'body' => $body,
            'data' => $data ?: null,
            'sent_at' => now(),
            'created_by' => $actor?->id,
        ]);
    }
}
