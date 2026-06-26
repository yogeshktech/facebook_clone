<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Post;
use App\Support\MediaStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GroupController extends Controller
{
    public function index(): View
    {
        $myGroups = auth()->user()->groups()->get();
        $discoverGroups = Group::where('privacy', 'public')
            ->whereNotIn('id', $myGroups->pluck('id'))
            ->latest()
            ->limit(10)
            ->get();

        return view('groups.index', compact('myGroups', 'discoverGroups'));
    }

    public function create(): View
    {
        return view('groups.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'privacy' => ['required', 'in:public,private'],
            'avatar' => ['nullable', 'image', 'max:2048'],
            'cover_photo' => ['nullable', 'image', 'max:5120'],
        ]);

        $group = Group::create([
            'owner_id' => auth()->id(),
            'name' => $validated['name'],
            'slug' => Str::slug($validated['name']).'-'.Str::random(5),
            'description' => $validated['description'] ?? null,
            'privacy' => $validated['privacy'],
        ]);

        if ($request->hasFile('avatar')) {
            $group->update(['avatar' => MediaStorage::store($request->file('avatar'), 'groups')]);
        }

        if ($request->hasFile('cover_photo')) {
            $group->update(['cover_photo' => MediaStorage::store($request->file('cover_photo'), 'groups')]);
        }

        $group->members()->attach(auth()->id(), ['role' => 'admin', 'status' => 'approved']);

        return redirect()->route('groups.show', $group)->with('success', 'Group created!');
    }

    public function show(Group $group): View
    {
        $isMember = $group->members()->where('user_id', auth()->id())->exists();
        $posts = Post::with(['user', 'likes', 'comments.user'])
            ->where('group_id', $group->id)
            ->latest()
            ->paginate(10);

        return view('groups.show', compact('group', 'isMember', 'posts'));
    }

    public function join(Group $group): RedirectResponse
    {
        if ($group->members()->where('user_id', auth()->id())->exists()) {
            return back()->with('error', 'Already a member.');
        }

        $status = $group->privacy === 'public' ? 'approved' : 'pending';
        $group->members()->attach(auth()->id(), ['role' => 'member', 'status' => $status]);

        return back()->with('success', $status === 'approved' ? 'Joined group!' : 'Join request sent!');
    }

    public function leave(Group $group): RedirectResponse
    {
        $group->members()->detach(auth()->id());

        return redirect()->route('groups.index')->with('success', 'Left group.');
    }
}
