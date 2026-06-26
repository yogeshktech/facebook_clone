<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Like;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostInteractionNotification;
use App\Services\NotificationService;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $friendIds = $this->getFriendIds($user);
        $followingIds = $user->following()->pluck('users.id')->toArray();
        $feedUserIds = array_unique(array_merge([$user->id], $friendIds, $followingIds));

        $posts = Post::with(['user', 'sharedPost.user', 'likes', 'comments.user'])
            ->whereIn('user_id', $feedUserIds)
            ->latest()
            ->paginate(15);

        return response()->json($posts);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:5000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm', 'max:51200'],
            'group_id' => ['nullable', 'exists:groups,id'],
            'page_id' => ['nullable', 'exists:pages,id'],
        ]);

        $type = 'text';
        $mediaPath = null;

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $type = MediaStorage::mediaType($file);
            $mediaPath = MediaStorage::store($file, 'posts');
        }

        $post = Post::create([
            'user_id' => $request->user()->id,
            'content' => $validated['content'] ?? null,
            'type' => $type,
            'media_path' => $mediaPath,
            'group_id' => $validated['group_id'] ?? null,
            'page_id' => $validated['page_id'] ?? null,
        ]);

        return response()->json($post->load('user'), 201);
    }

    public function show(Post $post): JsonResponse
    {
        return response()->json($post->load(['user', 'likes', 'comments.user']));
    }

    public function destroy(Post $post): JsonResponse
    {
        abort_unless($post->user_id === auth()->id(), 403);
        $post->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function like(Post $post): JsonResponse
    {
        $existing = Like::where('user_id', auth()->id())
            ->where('likeable_id', $post->id)
            ->where('likeable_type', Post::class)
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['liked' => false]);
        }

        Like::create([
            'user_id' => auth()->id(),
            'likeable_id' => $post->id,
            'likeable_type' => Post::class,
        ]);

        if ($post->user_id !== auth()->id()) {
            NotificationService::send(
                $post->user,
                new PostInteractionNotification(auth()->user(), $post, 'like')
            );
        }

        return response()->json(['liked' => true]);
    }

    public function comment(Request $request, Post $post): JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        $parentId = null;

        if (! empty($validated['parent_id'])) {
            $parent = Comment::where('id', $validated['parent_id'])
                ->where('post_id', $post->id)
                ->firstOrFail();

            $parentId = $parent->parent_id ?? $parent->id;
        }

        $comment = Comment::create([
            'user_id' => auth()->id(),
            'post_id' => $post->id,
            'parent_id' => $parentId,
            'content' => $validated['content'],
        ]);

        if ($post->user_id !== auth()->id()) {
            NotificationService::send(
                $post->user,
                new PostInteractionNotification(auth()->user(), $post, 'comment')
            );
        }

        return response()->json($comment->load(['user', 'replies.user']), 201);
    }

    public function share(Post $post): JsonResponse
    {
        $shared = Post::create([
            'user_id' => auth()->id(),
            'shared_post_id' => $post->id,
            'type' => 'text',
        ]);

        $post->increment('shares_count');

        return response()->json($shared->load('sharedPost'), 201);
    }

    private function getFriendIds(User $user): array
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');

        return $sent->merge($received)->unique()->toArray();
    }
}
