<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\Post;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $myPages = $user->pages()->get();
        $followedPages = $user->followedPages()->get();
        $discoverPages = Page::whereNotIn('id', $myPages->pluck('id')->merge($followedPages->pluck('id')))
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'my_pages' => $myPages,
            'followed' => $followedPages,
            'discover' => $discoverPages,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'category' => ['nullable', 'string', 'max:100'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'cover_photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $page = Page::create([
            'owner_id' => auth()->id(),
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::random(5),
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
        ]);

        if ($request->hasFile('avatar')) {
            $page->update(['avatar' => MediaStorage::store($request->file('avatar'), 'pages')]);
        }

        if ($request->hasFile('cover_photo')) {
            $page->update(['cover_photo' => MediaStorage::store($request->file('cover_photo'), 'pages')]);
        }

        return response()->json($page->load('owner'), 201);
    }

    public function show(Page $page): JsonResponse
    {
        $isFollowing = $page->followers()->where('user_id', auth()->id())->exists();
        $isOwner = $page->owner_id === auth()->id();
        $posts = Post::with(['user', 'likes', 'comments.user'])
            ->where('page_id', $page->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'page' => $page->load('owner'),
            'is_following' => $isFollowing,
            'is_owner' => $isOwner,
            'posts' => $posts,
        ]);
    }

    public function follow(Page $page): JsonResponse
    {
        if (! $page->followers()->where('user_id', auth()->id())->exists()) {
            $page->followers()->attach(auth()->id());
        }

        return response()->json(['following' => true]);
    }

    public function unfollow(Page $page): JsonResponse
    {
        $page->followers()->detach(auth()->id());

        return response()->json(['following' => false]);
    }
}
