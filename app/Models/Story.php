<?php

namespace App\Models;

use App\Support\MediaStorage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Story extends Model
{
    protected $fillable = ['user_id', 'media_path', 'media_type', 'caption', 'expires_at'];

    protected $appends = ['media_url'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function getMediaUrlAttribute(): string
    {
        return MediaStorage::url($this->media_path);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function views(): HasMany
    {
        return $this->hasMany(StoryView::class);
    }

    public function viewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'story_views')
            ->using(StoryViewPivot::class)
            ->withPivot('viewed_at')
            ->orderByPivot('viewed_at', 'desc');
    }

    public function recordView(int $userId): void
    {
        if ($userId === $this->user_id) {
            return;
        }

        StoryView::firstOrCreate(
            ['story_id' => $this->id, 'user_id' => $userId],
            ['viewed_at' => now()]
        );
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public static function pruneExpired(): int
    {
        $count = 0;

        static::where('expires_at', '<=', now())->each(function (self $story) use (&$count) {
            MediaStorage::delete($story->media_path);
            $story->delete();
            $count++;
        });

        return $count;
    }

    public static function groupedForFeed(array $userIds): Collection
    {
        if ($userIds === []) {
            return collect();
        }

        $query = static::with('user')->active()->whereIn('user_id', $userIds);

        if (Schema::hasTable('story_views')) {
            $query->withCount('views');
        }

        return $query->orderBy('created_at')
            ->get()
            ->groupBy('user_id')
            ->sortByDesc(fn (Collection $stories) => $stories->max('created_at'))
            ->map(fn (Collection $stories) => $stories->sortBy('created_at')->values());
    }

    public static function buildPlaylist(array $userIds): Collection
    {
        return static::groupedForFeed($userIds)
            ->flatMap(fn (Collection $stories) => $stories->pluck('id'))
            ->values();
    }

    public static function activeForUser(int $userId): Collection
    {
        return static::active()
            ->where('user_id', $userId)
            ->orderBy('created_at')
            ->get();
    }
}
