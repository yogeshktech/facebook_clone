<?php

namespace App\Models;

use App\Support\ChatEncryption;
use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Message extends Model
{
    protected $fillable = [
        'conversation_id', 'user_id', 'reply_to_id', 'body', 'message_type', 'call_status', 'call_is_video',
        'media_path', 'media_type', 'delivered_at', 'edited_at', 'deleted_for_everyone_at', 'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'delivered_at' => 'datetime',
            'edited_at' => 'datetime',
            'deleted_for_everyone_at' => 'datetime',
            'call_is_video' => 'boolean',
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
        if ($this->deleted_for_everyone_at || ! $this->media_path) {
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

    public function isDeletedForEveryone(): bool
    {
        return $this->deleted_for_everyone_at !== null;
    }

    public function isCall(): bool
    {
        return ($this->message_type ?? 'text') === 'call';
    }

    public function canEditBy(?int $userId = null): bool
    {
        $userId ??= auth()->id();
        if (! $userId || $this->user_id !== $userId || $this->isDeletedForEveryone() || $this->isCall()) {
            return false;
        }

        if ($this->media_path && ! $this->body) {
            return false;
        }

        $minutes = (int) config('chat.edit_window_minutes', 15);

        return $this->created_at && $this->created_at->gt(now()->subMinutes($minutes));
    }

    public function canDeleteForEveryoneBy(?int $userId = null): bool
    {
        $userId ??= auth()->id();
        if (! $userId || $this->user_id !== $userId || $this->isDeletedForEveryone() || $this->isCall()) {
            return false;
        }

        $minutes = (int) config('chat.delete_for_everyone_minutes', 60);

        return $this->created_at && $this->created_at->gt(now()->subMinutes($minutes));
    }

    public function callLabelFor(int $viewerId): string
    {
        $video = $this->call_is_video ? 'Video' : 'Voice';
        $isCaller = $this->user_id === $viewerId;

        return match ($this->call_status) {
            'declined' => $isCaller ? "{$video} call declined" : "Declined {$video} call",
            'unanswered' => $isCaller ? "{$video} call · No answer" : "Missed {$video} call",
            default => "{$video} call",
        };
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'reply_to_id');
    }

    public function hiddenForUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'message_user_deletes')->withTimestamps();
    }
}
