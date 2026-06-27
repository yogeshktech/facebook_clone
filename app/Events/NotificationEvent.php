<?php

namespace App\Events;

use App\Models\SocialNotification;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public SocialNotification $notification)
    {
        $this->notification->loadMissing('sender');
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('notification.'.$this->notification->receiver_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'NotificationEvent';
    }

    public function broadcastWith(): array
    {
        return $this->notification->toPayload();
    }
}
