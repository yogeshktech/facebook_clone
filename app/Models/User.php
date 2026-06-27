<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Support\MediaStorage;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'phone',
        'password',
        'role',
        'avatar',
        'cover_photo',
        'bio',
        'location',
        'website',
        'provider',
        'provider_id',
        'last_seen_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['avatar_url', 'cover_photo_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return MediaStorage::url($this->avatar)
                ?? 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=1877F2&color=fff';
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=1877F2&color=fff';
    }

    public function getCoverPhotoUrlAttribute(): ?string
    {
        return $this->cover_photo ? MediaStorage::url($this->cover_photo) : null;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function stories(): HasMany
    {
        return $this->hasMany(Story::class);
    }

    public function sentFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'user_id');
    }

    public function receivedFriendRequests(): HasMany
    {
        return $this->hasMany(Friendship::class, 'friend_id');
    }

    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')
            ->wherePivot('status', 'accepted')
            ->withTimestamps();
    }

    public function followers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'following_id', 'follower_id')
            ->withTimestamps();
    }

    public function following(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'following_id')
            ->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_members')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function ownedGroups(): HasMany
    {
        return $this->hasMany(Group::class, 'owner_id');
    }

    public function pages(): HasMany
    {
        return $this->hasMany(Page::class, 'owner_id');
    }

    public function followedPages(): BelongsToMany
    {
        return $this->belongsToMany(Page::class, 'page_followers')
            ->withTimestamps();
    }

    public function conversations(): BelongsToMany
    {
        return $this->belongsToMany(Conversation::class, 'conversation_user')
            ->withPivot('last_read_at')
            ->withTimestamps();
    }

    public function isFriendsWith(User $user): bool
    {
        return Friendship::where(function ($q) use ($user) {
            $q->where('user_id', $this->id)->where('friend_id', $user->id);
        })->orWhere(function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('friend_id', $this->id);
        })->where('status', 'accepted')->exists();
    }

    public function hasPendingRequestTo(User $user): bool
    {
        return Friendship::where('user_id', $this->id)
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->exists();
    }

    public function isFollowing(User $user): bool
    {
        return $this->following()->where('following_id', $user->id)->exists();
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'superadmin'], true);
    }

    public function isClient(): bool
    {
        return ! $this->isAdmin();
    }

    public function advertisements(): HasMany
    {
        return $this->hasMany(Advertisement::class);
    }

    public function receivedNotifications(): HasMany
    {
        return $this->hasMany(SocialNotification::class, 'receiver_id');
    }

    public function deviceTokens(): HasMany
    {
        return $this->hasMany(DeviceToken::class);
    }
}
