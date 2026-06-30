<?php

namespace App\Http\Controllers;

use App\Models\Advertisement;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class FeedController extends Controller
{
    public function index(Request $request): View|Response
    {
        try {
            Story::pruneExpired();
        } catch (\Throwable $e) {
            // Ignore
        }

        $user = $request->user();

        // Friend IDs
        $friendIds = $this->getFriendIds($user);

        // Following IDs
        $followingIds = $user->following()
            ->pluck('users.id')
            ->toArray();

        // Feed User IDs
        $feedUserIds = array_unique(array_merge(
            [$user->id],
            $friendIds,
            $followingIds
        ));

        // Feed Posts
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
            ->latest('id')
            ->cursorPaginate(5);

        /*
        |--------------------------------------------------------------------------
        | AJAX (Infinite Scroll)
        |--------------------------------------------------------------------------
        */

        if ($request->ajax()) {

            $reels = Post::with('user')
                ->withCount('likes')
                ->where('type', 'reel')
                ->latest('id')
                ->take(10)
                ->get();

            $activeAds = Advertisement::running()
                ->inRandomOrder()
                ->get();

            return response()->view('feed.partials.posts', [
                'posts' => $posts,
                'reels' => $reels,
                'activeAds' => $activeAds,
                'offset' => (int) $request->offset,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Stories
        |--------------------------------------------------------------------------
        */

        $stories = Story::groupedForFeed($feedUserIds);

        /*
        |--------------------------------------------------------------------------
        | Reels
        |--------------------------------------------------------------------------
        */

        $reels = Post::with('user')
            ->withCount('likes')
            ->where('type', 'reel')
            ->latest('id')
            ->take(10)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Suggestions
        |--------------------------------------------------------------------------
        */

        $suggestions = User::where('id', '!=', $user->id)
            ->whereNotIn('id', $friendIds)
            ->inRandomOrder()
            ->limit(12)
            ->get();

        /*
        |--------------------------------------------------------------------------
        | Ads
        |--------------------------------------------------------------------------
        */

        $activeAds = Advertisement::running()
            ->inRandomOrder()
            ->get();

        return view('feed.index', [
            'posts' => $posts,
            'stories' => $stories,
            'reels' => $reels,
            'suggestions' => $suggestions,
            'activeAds' => $activeAds,
            'offset' => 0,
        ]);
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
            ->values()
            ->toArray();
    }
}