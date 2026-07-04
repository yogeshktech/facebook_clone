<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Conversation extends Model
{
    protected $fillable = ['name', 'is_group'];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->withPivot(['last_read_at', 'hidden_at', 'role'])
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function isGroup(): bool
    {
        return (bool) $this->is_group;
    }

    public function displayNameFor(?int $userId = null): string
    {
        if ($this->isGroup()) {
            return $this->name ?: 'Group Chat';
        }

        $userId ??= auth()->id();
        $other = $this->users->firstWhere('id', '!=', $userId);

        return $other?->name ?? 'Chat';
    }

    public function avatarUrlFor(?int $userId = null): string
    {
        if ($this->isGroup()) {
            return 'https://ui-avatars.com/api/?name='.urlencode($this->displayNameFor($userId)).'&background=6366F1&color=fff';
        }

        $userId ??= auth()->id();
        $other = $this->users->firstWhere('id', '!=', $userId);

        return $other?->avatar_url ?? '';
    }

    public function isAdmin(int $userId): bool
    {
        $user = $this->users->firstWhere('id', $userId);

        return ($user?->pivot?->role ?? 'member') === 'admin';
    }

    public static function findBetweenUsers(int $userId1, int $userId2): ?self
    {
        return self::where('is_group', false)
            ->whereHas('users', fn ($q) => $q->where('user_id', $userId1))
            ->whereHas('users', fn ($q) => $q->where('user_id', $userId2))
            ->first();
    }
}
