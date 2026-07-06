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

class VideoController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $feedUserIds = $this->feedUserIds($user);

        $videos = Post::with(['user'])
            ->withCount(['likes', 'allComments as comments_count', 'reelViews as views_count'])
            ->where('type', 'video')
            ->whereIn('user_id', $feedUserIds)
            ->whereNull('group_id')
            ->whereNull('page_id')
            ->latest()
            ->cursorPaginate(10);

        return response()->json($videos);
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

        $video = Post::create([
            'user_id' => auth()->id(),
            'content' => trim($validated['content'] ?? '') ?: null,
            'type' => 'video',
            'media_path' => MediaStorage::store($file, 'videos'),
        ]);

        return response()->json($video->load('user'), 201);
    }

    public function show(Post $video): JsonResponse
    {
        abort_unless($video->type === 'video', 404);

        return response()->json($video->load(['user', 'allComments.user', 'likes']));
    }

    public function like(Post $video): JsonResponse
    {
        abort_unless($video->type === 'video', 404);

        $liked = false;

        if ($video->likes()->where('user_id', auth()->id())->exists()) {
            $video->likes()->where('user_id', auth()->id())->delete();
        } else {
            $video->likes()->create(['user_id' => auth()->id()]);
            $liked = true;

            if ($video->user_id !== auth()->id()) {
                NotificationService::postInteraction($video->user, auth()->user(), $video, 'like');
            }
        }

        return response()->json(['liked' => $liked, 'likes_count' => $video->likes()->count()]);
    }

    public function view(Post $video): JsonResponse
    {
        abort_unless($video->type === 'video', 404);

        $userId = auth()->id();
        if (! $video->reelViews()->where('user_id', $userId)->exists()) {
            $video->reelViews()->create(['user_id' => $userId]);
        }

        return response()->json(['views_count' => $video->reelViews()->count()]);
    }

    public function comment(Request $request, Post $video): JsonResponse
    {
        abort_unless($video->type === 'video', 404);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:500'],
        ]);

        $comment = $video->comments()->create([
            'user_id' => auth()->id(),
            'content' => $validated['content'],
        ]);

        if ($video->user_id !== auth()->id()) {
            NotificationService::postInteraction($video->user, auth()->user(), $video, 'comment');
        }

        return response()->json([
            'comment' => $comment->load('user'),
            'comments_count' => $video->comments()->count(),
        ], 201);
    }

    public function share(Post $video): JsonResponse
    {
        abort_unless($video->type === 'video', 404);

        $shared = Post::create([
            'user_id' => auth()->id(),
            'shared_post_id' => $video->id,
            'type' => 'text',
        ]);

        $video->increment('shares_count');

        return response()->json($shared->load('sharedPost'), 201);
    }

    public function sendToFriend(Post $video, User $user): JsonResponse
    {
        abort_unless($video->type === 'video', 404);
        abort_unless(auth()->user()->isFriendsWith($user), 403);

        $conversation = Conversation::findBetweenUsers(auth()->id(), $user->id);

        if (! $conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->users()->attach([auth()->id(), $user->id]);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'body' => auth()->user()->name.' shared a video with you (#'.$video->id.')',
        ]);

        $conversation->touch();
        broadcast(new MessageSent($message))->toOthers();
        NotificationService::chatMessage($conversation, auth()->user(), $message);
        $video->increment('shares_count');

        return response()->json(['message' => 'Video sent', 'conversation_id' => $conversation->id]);
    }

    private function feedUserIds(User $user): array
    {
        $friendIds = $user->friends()->pluck('users.id')->toArray();
        $followingIds = $user->following()->pluck('users.id')->toArray();

        return array_unique(array_merge([$user->id], $friendIds, $followingIds));
    }
}
