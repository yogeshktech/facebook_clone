<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

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
        $this->message->loadMissing(['user', 'replyTo.user']);

        $reply = null;
        if ($this->message->replyTo) {
            $r = $this->message->replyTo;
            $reply = [
                'id' => $r->id,
                'body' => $r->isDeletedForEveryone()
                    ? 'This message was deleted'
                    : Str::limit($r->body ?: ($r->media_path ? 'Media' : ''), 80),
                'user_name' => $r->user?->name,
                'user_id' => $r->user_id,
                'deleted_for_everyone' => $r->isDeletedForEveryone(),
            ];
        }

        return [
            'id' => $this->message->id,
            'body' => $this->message->body,
            'message_type' => $this->message->message_type ?? 'text',
            'call_status' => $this->message->call_status,
            'call_is_video' => $this->message->call_is_video,
            'media_url' => $this->message->media_url,
            'media_type' => $this->message->media_type,
            'is_edited' => false,
            'deleted_for_everyone' => false,
            'reply_to' => $reply,
            'user' => [
                'id' => $this->message->user->id,
                'name' => $this->message->user->name,
                'avatar_url' => $this->message->user->avatar_url,
            ],
            'user_id' => $this->message->user_id,
            'user_name' => $this->message->user->name,
            'created_at' => $this->message->created_at->toISOString(),
        ];
    }
}
