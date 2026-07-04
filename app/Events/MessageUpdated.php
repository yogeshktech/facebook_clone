<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Message $message, public string $action = 'updated') {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('conversation.'.$this->message->conversation_id),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message.updated';
    }

    public function broadcastWith(): array
    {
        $this->message->loadMissing(['user', 'replyTo.user']);

        $deleted = $this->message->isDeletedForEveryone();

        return [
            'action' => $this->action,
            'id' => $this->message->id,
            'body' => $deleted ? '' : $this->message->body,
            'deleted_for_everyone' => $deleted,
            'is_edited' => ! $deleted && $this->message->edited_at !== null,
            'media_url' => $this->message->media_url,
            'media_type' => $deleted ? null : $this->message->media_type,
            'user_id' => $this->message->user_id,
            'updated_at' => now()->toISOString(),
        ];
    }
}
