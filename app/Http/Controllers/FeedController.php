<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function index(Request $request): View
    {
        try {
            Story::pruneExpired();
        } catch (\Throwable) {
            // Do not block the feed if story cleanup fails.
        }

        $user = $request->user();
        $friendIds = $this->getFriendIds($user);
        $followingIds = $user->following()->pluck('users.id')->toArray();
        $feedUserIds = array_unique(array_merge([$user->id], $friendIds, $followingIds));

        $posts = Post::with(['user', 'sharedPost.user', 'comments.user', 'comments.replies.user'])
            ->withCount(['likes', 'comments'])
            ->whereIn('user_id', $feedUserIds)
            ->whereNull('group_id')
            ->whereNull('page_id')
            ->latest()
            ->paginate(10);

        $stories = Story::groupedForFeed($feedUserIds);

        $suggestions = User::where('id', '!=', $user->id)
            ->whereNotIn('id', $friendIds)
            ->inRandomOrder()
            ->limit(5)
            ->get();

        $activeAds = \App\Models\Advertisement::where('status', 'approved')
            ->where('payment_status', 'paid')
            ->where(function($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->inRandomOrder()
            ->get();

        return view('feed.index', compact('posts', 'stories', 'suggestions', 'activeAds'));
    }

    private function getFriendIds(User $user): array
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');

        return $sent->merge($received)->unique()->toArray();
    }
}
