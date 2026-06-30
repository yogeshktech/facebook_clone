<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use App\Models\Advertisement;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    public function index(Request $request): View|Response
    {
        try {
            Story::pruneExpired();
        } catch (\Throwable) {
            // Ignore cleanup errors
        }

        $user = $request->user();

        $friendIds = $this->getFriendIds($user);
        $followingIds = $user->following()->pluck('users.id')->toArray();

        $feedUserIds = array_unique(array_merge(
            [$user->id],
            $friendIds,
            $followingIds
        ));

        $posts = Post::with([
            'user',
            'sharedPost.user',
            'comments.user',
            'comments.replies.user'
        ])
            ->withCount([
                'likes',
                'comments'
            ])
            ->whereIn('user_id', $feedUserIds)
            ->where('type', '!=', 'reel')
            ->whereNull('group_id')
            ->whereNull('page_id')
            ->orderByDesc('id')
            ->cursorPaginate(5);

        /*
        |--------------------------------------------------------------------------
        | AJAX Request
        |--------------------------------------------------------------------------
        | Infinite scroll ke time sirf posts return hongi.
        */
        if ($request->ajax()) {

            return response()->view(
                'feed.partials.posts',
                [
                    'posts' => $posts,
                    'activeAds' => Advertisement::running()->inRandomOrder()->get(),
                ]
            );
        }

        $stories = Story::groupedForFeed($feedUserIds);

        $suggestions = User::where('id', '!=', $user->id)
            ->whereNotIn('id', $friendIds)
            ->inRandomOrder()
            ->limit(12)
            ->get();

        $activeAds = Advertisement::running()
            ->inRandomOrder()
            ->get();

        return view('feed.index', compact(
            'posts',
            'stories',
            'suggestions',
            'activeAds'
        ));
    }

    private function getFriendIds(User $user): array
    {
        $sent = $user->sentFriendRequests()
            ->where('status', 'accepted')
            ->pluck('friend_id');

        $received = $user->receivedFriendRequests()
            ->where('status', 'accepted')
            ->pluck('user_id');

        return $sent
            ->merge($received)
            ->unique()
            ->toArray();
    }
}