<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Page;
use App\Models\Story;
use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:51200'],
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
        return response()->json($user->loadCount(['posts', 'followers', 'following']));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'bio' => ['nullable', 'string', 'max:500'],
            'location' => ['nullable', 'string', 'max:255'],
            'website' => ['nullable', 'url'],
        ]);

        $user->update($validated);

        return response()->json($user);
    }
}
