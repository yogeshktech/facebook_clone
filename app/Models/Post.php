<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Post extends Model
{
    protected $fillable = [
        'user_id', 'group_id', 'page_id', 'shared_post_id',
        'content', 'type', 'media_path', 'shares_count',
    ];

    protected $appends = ['media_url', 'likes_count', 'comments_count', 'is_liked'];

    public function getMediaUrlAttribute(): ?string
    {
        return $this->media_path ? MediaStorage::url($this->media_path) : null;
    }

    public function getLikesCountAttribute(): int
    {
        if (array_key_exists('likes_count', $this->attributes)) {
            return (int) $this->attributes['likes_count'];
        }

        return $this->likes()->count();
    }

    public function getCommentsCountAttribute(): int
    {
        if (array_key_exists('comments_count', $this->attributes)) {
            return (int) $this->attributes['comments_count'];
        }

        return $this->comments()->count();
    }

    public function getIsLikedAttribute(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        return $this->likes()->where('user_id', auth()->id())->exists();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function sharedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'shared_post_id');
    }

    public function shares(): HasMany
    {
        return $this->hasMany(Post::class, 'shared_post_id');
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->whereNull('parent_id')->latest();
    }

    public function allComments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
