<?php

namespace App\Models;

use App\Support\ChatEncryption;
use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'user_id', 'body', 'media_path', 'media_type', 'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
        ];
    }

    protected $appends = ['media_url', 'is_sender'];

    protected function body(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => ChatEncryption::decrypt($value),
            set: fn (?string $value) => ChatEncryption::encrypt($value ?? ''),
        );
    }

    public function getRawBody(): string
    {
        return $this->attributes['body'] ?? '';
    }

    public function getMediaUrlAttribute(): ?string
    {
        if (! $this->media_path) {
            return null;
        }

        if (ChatEncryption::isEncryptedMedia($this->media_path)) {
            return route('chat.media', $this->id);
        }

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
