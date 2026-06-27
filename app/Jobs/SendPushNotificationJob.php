<?php

namespace App\Jobs;

use App\Models\SocialNotification;
use App\Services\FirebasePushService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public SocialNotification $notification) {}

    public function handle(FirebasePushService $firebase): void
    {
        $firebase->sendToUser($this->notification);
    }
}
