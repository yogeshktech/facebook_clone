<?php

namespace App\Services;

use App\Events\LiveNotification;
use App\Models\User;
use Illuminate\Notifications\Notification;

class NotificationService
{
    public static function send(User $user, Notification $notification): void
    {
        $user->notify($notification);

        $latest = $user->notifications()->latest()->first();
        if ($latest) {
            event(new LiveNotification($user->id, $latest->toArray()));
        }
    }
}
