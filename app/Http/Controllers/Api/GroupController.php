<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Post;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $myGroups = $request->user()->groups()->get();
        $discoverGroups = Group::where('privacy', 'public')
            ->whereNotIn('id', $myGroups->pluck('id'))
            ->latest()
            ->limit(10)
            ->get();

        return response()->json([
            'my_groups' => $myGroups,
            'discover' => $discoverGroups,
        ]);
    }

    public function store(Request $request): JsonResponse
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

        return response()->json($group->load('owner'), 201);
    }

    public function show(Group $group): JsonResponse
    {
        $isMember = $group->members()->where('user_id', auth()->id())->exists();
        $posts = Post::with(['user', 'likes', 'comments.user'])
            ->where('group_id', $group->id)
            ->latest()
            ->paginate(10);

        return response()->json([
            'group' => $group->load('owner'),
            'is_member' => $isMember,
            'posts' => $posts,
        ]);
    }

    public function join(Group $group): JsonResponse
    {
        if ($group->members()->where('user_id', auth()->id())->exists()) {
            return response()->json(['message' => 'Already a member'], 422);
        }

        $status = $group->privacy === 'public' ? 'approved' : 'pending';
        $group->members()->attach(auth()->id(), ['role' => 'member', 'status' => $status]);

        return response()->json([
            'joined' => $status === 'approved',
            'status' => $status,
        ]);
    }

    public function leave(Group $group): JsonResponse
    {
        $group->members()->detach(auth()->id());

        return response()->json(['message' => 'Left group']);
    }
}
