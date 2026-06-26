<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Group extends Model
{
    protected $fillable = [
        'owner_id', 'name', 'slug', 'description',
        'avatar', 'cover_photo', 'privacy',
    ];

    protected $appends = ['avatar_url', 'cover_photo_url', 'members_count'];

    protected static function booted(): void
    {
        static::creating(function (Group $group) {
            if (empty($group->slug)) {
                $group->slug = Str::slug($group->name).'-'.Str::random(5);
            }
        });
    }

    public function getAvatarUrlAttribute(): string
    {
        if ($this->avatar) {
            return MediaStorage::url($this->avatar);
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=42b72a&color=fff';
    }

    public function getCoverPhotoUrlAttribute(): ?string
    {
        return $this->cover_photo ? MediaStorage::url($this->cover_photo) : null;
    }

    public function getMembersCountAttribute(): int
    {
        return $this->members()->wherePivot('status', 'approved')->count();
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'group_members')
            ->withPivot(['role', 'status'])
            ->withTimestamps();
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
