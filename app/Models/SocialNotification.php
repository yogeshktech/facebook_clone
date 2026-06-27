<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialNotification extends Model
{
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'type',
        'title',
        'message',
        'reference_id',
        'url',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function toPayload(): array
    {
        return [
            'id' => $this->id,
            'sender_id' => $this->sender_id,
            'receiver_id' => $this->receiver_id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'reference_id' => $this->reference_id,
            'url' => $this->url,
            'is_read' => $this->is_read,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at?->diffForHumans(),
            'sender' => $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'avatar_url' => $this->sender->avatar_url,
            ] : null,
        ];
    }
}
