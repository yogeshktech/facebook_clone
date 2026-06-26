<?php

namespace App\Http\Controllers;

use App\Models\Friendship;
use App\Models\User;
use App\Notifications\FriendRequestNotification;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FriendController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $pendingRequests = Friendship::with('user')
            ->where('friend_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        $sentRequests = Friendship::with('friend')
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->latest()
            ->get();

        $friendIds = $this->getFriendIds($user);
        $friends = User::whereIn('id', $friendIds)->get();

        return view('friends.index', compact('pendingRequests', 'sentRequests', 'friends'));
    }

    public function send(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->id === $user->id) {
            return back()->with('error', 'You cannot send a friend request to yourself.');
        }

        if ($request->user()->isFriendsWith($user)) {
            return back()->with('error', 'You are already friends.');
        }

        $existing = Friendship::where(function ($q) use ($request, $user) {
            $q->where('user_id', $request->user()->id)->where('friend_id', $user->id);
        })->orWhere(function ($q) use ($request, $user) {
            $q->where('user_id', $user->id)->where('friend_id', $request->user()->id);
        })->first();

        if ($existing) {
            return back()->with('error', 'Friend request already exists.');
        }

        $friendship = Friendship::create([
            'user_id' => $request->user()->id,
            'friend_id' => $user->id,
            'status' => 'pending',
        ]);

        NotificationService::send($user, new FriendRequestNotification($friendship));

        return back()->with('success', 'Friend request sent!');
    }

    public function accept(Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->friend_id === auth()->id(), 403);

        $friendship->update(['status' => 'accepted']);

        return back()->with('success', 'Friend request accepted!');
    }

    public function reject(Friendship $friendship): RedirectResponse
    {
        abort_unless($friendship->friend_id === auth()->id(), 403);

        $friendship->update(['status' => 'rejected']);

        return back()->with('success', 'Friend request rejected.');
    }

    public function unfriend(User $user): RedirectResponse
    {
        Friendship::where(function ($q) use ($user) {
            $q->where('user_id', auth()->id())->where('friend_id', $user->id);
        })->orWhere(function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('friend_id', auth()->id());
        })->delete();

        return back()->with('success', 'Friend removed.');
    }

    private function getFriendIds(User $user): array
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');

        return $sent->merge($received)->unique()->toArray();
    }
}
