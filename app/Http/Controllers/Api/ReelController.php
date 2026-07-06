<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Like;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use App\Services\ContentModerationService;
use App\Services\NotificationService;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReelController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $feedUserIds = $this->feedUserIds($user);

        $reels = Post::with(['user'])
            ->withCount(['likes', 'allComments as comments_count', 'reelViews as views_count'])
            ->where('type', 'reel')
            ->whereIn('user_id', $feedUserIds)
            ->whereNull('group_id')
            ->whereNull('page_id')
            ->latest()
            ->cursorPaginate(10);

        return response()->json($reels);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'media' => ['required', 'file', 'mimes:mp4,webm,mov', 'max:'.config('media.max_video_kb')],
            'content' => ['nullable', 'string', 'max:500'],
        ]);

        if (ContentModerationService::isProfane($validated['content'] ?? '')) {
            return response()->json(['message' => 'Inappropriate language detected.'], 422);
        }

        $file = $request->file('media');
        if (ContentModerationService::isAdult($file)) {
            return response()->json(['message' => 'Inappropriate content detected.'], 422);
        }

        $reel = Post::create([
            'user_id' => auth()->id(),
            'content' => trim($validated['content'] ?? '') ?: null,
            'type' => 'reel',
            'media_path' => MediaStorage::store($file, 'reels'),
        ]);

        return response()->json($reel->load('user'), 201);
    }

    public function like(Post $reel): JsonResponse
    {
        abort_unless($reel->type === 'reel', 404);

        $existing = Like::where('user_id', auth()->id())
            ->where('likeable_id', $reel->id)
            ->where('likeable_type', Post::class)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['liked' => false, 'likes_count' => $reel->likes()->count()]);
        }

        Like::create([
            'user_id' => auth()->id(),
            'likeable_id' => $reel->id,
            'likeable_type' => Post::class,
        ]);

        if ($reel->user_id !== auth()->id()) {
            NotificationService::postInteraction($reel->user, auth()->user(), $reel, 'like');
        }

        return response()->json(['liked' => true, 'likes_count' => $reel->likes()->count()]);
    }

    public function comment(Request $request, Post $reel): JsonResponse
    {
        abort_unless($reel->type === 'reel', 404);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $comment = Comment::create([
            'user_id' => auth()->id(),
            'post_id' => $reel->id,
            'content' => $validated['content'],
        ]);

        if ($reel->user_id !== auth()->id()) {
            NotificationService::postInteraction($reel->user, auth()->user(), $reel, 'comment');
        }

        return response()->json([
            'comment' => $comment->load('user'),
            'comments_count' => $reel->comments()->count(),
        ], 201);
    }

    public function share(Post $reel): JsonResponse
    {
        abort_unless($reel->type === 'reel', 404);

        $shared = Post::create([
            'user_id' => auth()->id(),
            'shared_post_id' => $reel->id,
            'type' => 'text',
        ]);

        $reel->increment('shares_count');

        if ($reel->user_id !== auth()->id()) {
            NotificationService::postInteraction($reel->user, auth()->user(), $reel, 'share');
        }

        return response()->json($shared->load('sharedPost'), 201);
    }

    public function sendToFriend(Post $reel, User $user): JsonResponse
    {
        abort_unless($reel->type === 'reel', 404);
        abort_unless(auth()->user()->isFriendsWith($user), 403);

        $conversation = Conversation::findBetweenUsers(auth()->id(), $user->id);

        if (! $conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->users()->attach([auth()->id(), $user->id]);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'body' => auth()->user()->name.' shared a reel with you (#'.$reel->id.')',
        ]);

        $conversation->touch();
        broadcast(new MessageSent($message))->toOthers();
        NotificationService::chatMessage($conversation, auth()->user(), $message);
        $reel->increment('shares_count');

        return response()->json(['message' => 'Reel sent', 'conversation_id' => $conversation->id]);
    }

    public function view(Request $request, Post $reel): JsonResponse
    {
        abort_unless($reel->type === 'reel', 404);

        $reel->recordReelView($request->user()->id);

        return response()->json(['views_count' => $reel->reelViews()->count()]);
    }

    private function feedUserIds(User $user): array
    {
        $friendIds = $this->getFriendIds($user);
        $followingIds = $user->following()->pluck('users.id')->toArray();

        return array_unique(array_merge([$user->id], $friendIds, $followingIds));
    }

    private function getFriendIds(User $user): array
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');

        return $sent->merge($received)->unique()->toArray();
    }
}
