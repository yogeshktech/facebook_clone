<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\Group;
use App\Models\Page;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ResourceController extends Controller
{
    public function stories(Request $request): JsonResponse
    {
        Story::pruneExpired();

        $stories = Story::with('user')
            ->active()
            ->orderBy('created_at')
            ->get()
            ->groupBy('user_id')
            ->sortByDesc(fn ($userStories) => $userStories->max('created_at'))
            ->map(fn ($userStories) => $userStories->sortBy('created_at')->values());

        return response()->json($stories);
    }

    public function storeStory(Request $request): JsonResponse
    {
        Story::pruneExpired();
        $validated = $request->validate([
            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:'.config('media.max_video_kb')],
            'caption' => ['nullable', 'string', 'max:500'],
        ]);

        $file = $request->file('media');
        $story = Story::create([
            'user_id' => auth()->id(),
            'media_path' => MediaStorage::store($file, 'stories'),
            'media_type' => MediaStorage::mediaType($file),
            'caption' => $validated['caption'] ?? null,
            'expires_at' => now()->addHours(24),
        ]);

        return response()->json($story->load('user'), 201);
    }

    public function showStory(Story $story): JsonResponse
    {
        Story::pruneExpired();

        if (! $story->expires_at || $story->expires_at->isPast()) {
            return response()->json(['message' => 'Story expired'], 404);
        }

        if (Schema::hasTable('story_views')) {
            try {
                $story->recordView(auth()->id());
            } catch (\Throwable) {
                // ignore
            }
        }

        return response()->json($story->load('user'));
    }

    public function destroyStory(Story $story): JsonResponse
    {
        abort_unless($story->user_id === auth()->id(), 403);

        MediaStorage::delete($story->media_path);
        $story->delete();

        return response()->json(['message' => 'Story deleted']);
    }

    public function storyViewers(Story $story): JsonResponse
    {
        abort_unless($story->user_id === auth()->id(), 403);

        if (! $story->expires_at || $story->expires_at->isPast()) {
            return response()->json(['message' => 'Story expired'], 404);
        }

        $viewers = Schema::hasTable('story_views')
            ? $story->viewers()->get()
            : collect();

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

    public function groups(): JsonResponse
    {
        return response()->json(Group::with('owner')->latest()->paginate(15));
    }

    public function pages(): JsonResponse
    {
        return response()->json(Page::with('owner')->latest()->paginate(15));
    }

    public function search(Request $request): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return response()->json(['users' => [], 'groups' => [], 'pages' => []]);
        }

        return response()->json([
            'users' => User::where('name', 'like', "%{$query}%")->limit(20)->get(),
            'groups' => Group::where('name', 'like', "%{$query}%")->limit(10)->get(),
            'pages' => Page::where('name', 'like', "%{$query}%")->limit(10)->get(),
        ]);
    }

    public function follow(User $user): JsonResponse
    {
        auth()->user()->following()->syncWithoutDetaching([$user->id]);

        return response()->json(['following' => true]);
    }

    public function unfollow(User $user): JsonResponse
    {
        auth()->user()->following()->detach($user->id);

        return response()->json(['following' => false]);
    }

    public function notifications(Request $request): JsonResponse
    {
        $notifications = \App\Models\SocialNotification::where('receiver_id', $request->user()->id)
            ->with('sender')
            ->latest()
            ->paginate(20);

        return response()->json($notifications->through(fn ($n) => $n->toPayload()));
    }

    public function profile(User $user): JsonResponse
    {
        $authUser = auth()->user();
        $posts = Post::with(['user', 'likes', 'comments.user'])
            ->where('user_id', $user->id)
            ->where('type', '!=', 'reel')
            ->whereNull('group_id')
            ->whereNull('page_id')
            ->latest()
            ->paginate(10);

        $incomingRequest = Friendship::where('user_id', $user->id)
            ->where('friend_id', $authUser->id)
            ->where('status', 'pending')
            ->first();

        return response()->json([
            'user' => $user->loadCount(['posts', 'followers', 'following']),
            'posts' => $posts,
            'is_friend' => $authUser->isFriendsWith($user),
            'has_pending_request' => $authUser->hasPendingRequestTo($user),
            'incoming_request' => $incomingRequest,
            'is_following' => $authUser->isFollowing($user),
        ]);
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255', 'unique:users,username,'.$user->id],
            'bio' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url', 'max:255'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'cover_photo' => ['nullable', 'image', 'max:5120'],
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                MediaStorage::delete($user->avatar);
            }
            $validated['avatar'] = MediaStorage::store($request->file('avatar'), 'avatars');
        }

        if ($request->hasFile('cover_photo')) {
            if ($user->cover_photo) {
                MediaStorage::delete($user->cover_photo);
            }
            $validated['cover_photo'] = MediaStorage::store($request->file('cover_photo'), 'covers');
        }

        $user->update($validated);

        return response()->json($user);
    }
}
