<?php

namespace App\Http\Controllers;

use App\Models\Page;
use App\Models\Post;
use App\Support\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PageController extends Controller
{
    public function index(): View
    {
        $myPages = auth()->user()->pages()->get();
        $followedPages = auth()->user()->followedPages()->get();
        $discoverPages = Page::whereNotIn('id', $myPages->pluck('id')->merge($followedPages->pluck('id')))
            ->latest()
            ->limit(10)
            ->get();

        return view('pages.index', compact('myPages', 'followedPages', 'discoverPages'));
    }

    public function create(): View
    {
        return view('pages.create');
    }

    public function store(Request $request): RedirectResponse
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

        return redirect()->route('pages.show', $page)->with('success', 'Page created!');
    }

    public function show(Page $page): View
    {
        $isFollowing = $page->followers()->where('user_id', auth()->id())->exists();
        $isOwner = $page->owner_id === auth()->id();
        $posts = Post::with(['user', 'likes', 'comments.user'])
            ->where('page_id', $page->id)
            ->latest()
            ->paginate(10);

        return view('pages.show', compact('page', 'isFollowing', 'isOwner', 'posts'));
    }

    public function follow(Page $page): RedirectResponse
    {
        if (! $page->followers()->where('user_id', auth()->id())->exists()) {
            $page->followers()->attach(auth()->id());
        }

        return back()->with('success', 'Page followed!');
    }

    public function unfollow(Page $page): RedirectResponse
    {
        $page->followers()->detach(auth()->id());

        return back()->with('success', 'Page unfollowed.');
    }
}
