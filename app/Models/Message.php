<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'user_id', 'body', 'media_path', 'media_type',
    ];

    protected $appends = ['media_url', 'is_sender'];

    public function getMediaUrlAttribute(): ?string
    {
        return MediaStorage::url($this->media_path);
    }

    public function getIsSenderAttribute(): bool
    {
        return auth()->check() && $this->user_id === auth()->id();
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
