<?php

namespace App\Http\Controllers;

use App\Models\Story;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class StoryController extends Controller
{
    public function index(): View
    {
        Story::pruneExpired();

        $feedUserIds = $this->feedUserIds(auth()->user());
        $stories = Story::groupedForFeed($feedUserIds);

        return view('stories.index', compact('stories'));
    }

    public function store(Request $request): RedirectResponse
    {
        Story::pruneExpired();

        $validated = $request->validate([
            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $file = $request->file('media');
            $mediaType = MediaStorage::mediaType($file);

            Story::create([
                'user_id' => auth()->id(),
                'media_path' => MediaStorage::store($file, 'stories'),
                'media_type' => $mediaType,
                'caption' => $validated['caption'] ?? null,
                'expires_at' => now()->addHours(24),
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Story upload failed: '.$e->getMessage());
        }

        $firstStory = Story::active()
            ->where('user_id', auth()->id())
            ->orderBy('created_at')
            ->first();

        return redirect()
            ->route('stories.show', $firstStory)
            ->with('success', 'Story posted! It will be visible for 24 hours.');
    }

    public function show(Story $story): View
    {
        Story::pruneExpired();

        if (! $story->expires_at || $story->expires_at->isPast()) {
            abort(404, 'Story expired or not available.');
        }

        $this->safeRecordView($story);

        $isOwner = auth()->id() === $story->user_id;
        $viewCount = $this->hasViewsTable() ? $story->views()->count() : 0;
        $viewers = ($isOwner && $this->hasViewsTable())
            ? $story->viewers()->get()
            : collect();

        $userStories = Story::activeForUser($story->user_id);
        $userStoryIndex = $userStories->search(fn (Story $s) => $s->id === $story->id);

        $playlist = Story::buildPlaylist($this->feedUserIds(auth()->user()));
        $currentIndex = $playlist->search($story->id);

        $nextStoryId = ($currentIndex !== false) ? $playlist->get($currentIndex + 1) : null;
        $prevStoryId = ($currentIndex !== false && $currentIndex > 0) ? $playlist->get($currentIndex - 1) : null;

        $nextUrl = $nextStoryId
            ? route('stories.show', $nextStoryId)
            : route('feed.index');

        $prevUrl = $prevStoryId
            ? route('stories.show', $prevStoryId)
            : route('feed.index');

        return view('stories.show', compact(
            'story',
            'isOwner',
            'viewCount',
            'viewers',
            'nextUrl',
            'prevUrl',
            'userStories',
            'userStoryIndex',
        ));
    }

    public function destroy(Story $story): RedirectResponse
    {
        abort_unless($story->user_id === auth()->id(), 403);

        MediaStorage::delete($story->media_path);
        $story->delete();

        return redirect()->route('feed.index')->with('success', 'Story deleted.');
    }

    public function viewers(Story $story): JsonResponse|View
    {
        abort_unless($story->user_id === auth()->id(), 403);

        if (! $story->expires_at || $story->expires_at->isPast()) {
            abort(404);
        }

        $viewers = $this->hasViewsTable()
            ? $story->viewers()->get()
            : collect();

        if (request()->expectsJson()) {
            return response()->json([
                'view_count' => $viewers->count(),
                'viewers' => $viewers->map(fn ($user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_url,
                    'viewed_at' => $user->pivot->viewed_at?->diffForHumans(),
                ]),
            ]);
        }

        return view('stories.viewers', compact('story', 'viewers'));
    }

    private function safeRecordView(Story $story): void
    {
        if (! $this->hasViewsTable()) {
            return;
        }

        try {
            $story->recordView(auth()->id());
        } catch (\Throwable) {
            // ignore if views table not ready
        }
    }

    private function hasViewsTable(): bool
    {
        return Schema::hasTable('story_views');
    }

    private function feedUserIds($user): array
    {
        $friendIds = $this->getFriendIds($user);

        return array_unique(array_merge([$user->id], $friendIds));
    }

    private function getFriendIds($user): array
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');

        return $sent->merge($received)->unique()->toArray();
    }
}
