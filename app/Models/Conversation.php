<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    protected $fillable = ['name', 'is_group'];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->latest();
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public static function findBetweenUsers(int $userId1, int $userId2): ?self
    {
        return self::where('is_group', false)
            ->whereHas('users', fn ($q) => $q->where('user_id', $userId1))
            ->whereHas('users', fn ($q) => $q->where('user_id', $userId2))
            ->first();
    }
}
