<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use App\Support\MediaStorage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function show(User $user): View
    {
        $authUser = auth()->user();
        $posts = Post::with(['user', 'likes', 'comments.user', 'comments.replies.user'])
            ->where('user_id', $user->id)
            ->where('type', '!=', 'reel')
            ->whereNull('group_id')
            ->whereNull('page_id')
            ->latest()
            ->paginate(10);

        $isFriend = $authUser->isFriendsWith($user);
        $hasPendingRequest = $authUser->hasPendingRequestTo($user);
        $incomingRequest = \App\Models\Friendship::where('user_id', $user->id)
            ->where('friend_id', $authUser->id)
            ->where('status', 'pending')
            ->first();
        $isFollowing = $authUser->isFollowing($user);
        $friendsCount = $this->friendsCount($user);

        return view('profile.show', compact(
            'user', 'posts', 'isFriend', 'hasPendingRequest', 'incomingRequest', 'isFollowing', 'friendsCount'
        ));
    }

    public function edit(): View
    {
        return view('profile.edit', ['user' => auth()->user()]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
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

        return redirect()->route('profile.show', $user)->with('success', 'Profile updated successfully!');
    }

    private function friendsCount(User $user): int
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->count();
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->count();

        return $sent + $received;
    }
}
