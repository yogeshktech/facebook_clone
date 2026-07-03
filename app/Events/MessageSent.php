<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        $this->message->load('user');

        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'message_type' => $this->message->message_type ?? 'text',
            'call_status' => $this->message->call_status,
            'call_is_video' => $this->message->call_is_video,
            'media_url' => $this->message->media_url,
            'media_type' => $this->message->media_type,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'avatar_url' => $this->message->user->avatar_url,
            ],
            'user_id' => $this->message->user_id,
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }
}
