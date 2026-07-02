<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CallSignalingEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $fromUserId,
        public int $toUserId,
        public string $type,
        public array $data = []
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('user-signaling.'.$this->toUserId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'call.signal';
    }

    public function broadcastWith(): array
    {
        $fromUser = \App\Models\User::find($this->fromUserId);

        return [
            'from_user' => [
                'id' => $fromUser?->id,
                'name' => $fromUser?->name,
                'avatar_url' => $fromUser?->avatar_url,
            ],
            'type' => $this->type,
            'data' => $this->data,
        ];
    }
}
