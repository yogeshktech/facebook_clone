<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\MessageSent;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Like;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
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
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:'.config('media.max_video_kb')],
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
            NotificationService::postInteraction($post->user, auth()->user(), $post, 'like');
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

        $content = trim($validated['content']);

        $isDuplicate = Comment::where('user_id', auth()->id())
            ->where('post_id', $post->id)
            ->where('content', $content)
            ->where(function ($query) use ($parentId) {
                if ($parentId === null) {
                    $query->whereNull('parent_id');
                } else {
                    $query->where('parent_id', $parentId);
                }
            })
            ->where('created_at', '>=', now()->subSeconds(15))
            ->exists();

        if ($isDuplicate) {
            $existing = Comment::where('user_id', auth()->id())
                ->where('post_id', $post->id)
                ->where('content', $content)
                ->latest()
                ->first();

            return response()->json($existing->load(['user', 'replies.user']));
        }

        $comment = Comment::create([
            'user_id' => auth()->id(),
            'post_id' => $post->id,
            'parent_id' => $parentId,
            'content' => $content,
        ]);

        if ($post->user_id !== auth()->id()) {
            NotificationService::postInteraction($post->user, auth()->user(), $post, 'comment');
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

        if ($post->user_id !== auth()->id()) {
            NotificationService::postInteraction($post->user, auth()->user(), $post, 'share');
        }

        return response()->json($shared->load('sharedPost'), 201);
    }

    public function likers(Post $post): JsonResponse
    {
        $likers = $post->likes()
            ->with('user')
            ->latest()
            ->get()
            ->map(fn (Like $like) => [
                'id' => $like->user->id,
                'name' => $like->user->name,
                'avatar_url' => $like->user->avatar_url,
            ]);

        return response()->json(['likers' => $likers]);
    }

    public function sendToFriend(Post $post, User $user): JsonResponse
    {
        abort_unless(auth()->user()->isFriendsWith($user), 403);

        $conversation = Conversation::findBetweenUsers(auth()->id(), $user->id);

        if (! $conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->users()->attach([auth()->id(), $user->id]);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'body' => auth()->user()->name.' shared a post with you (#'.$post->id.')',
        ]);

        $conversation->touch();
        broadcast(new MessageSent($message))->toOthers();
        NotificationService::chatMessage($conversation, auth()->user(), $message);
        $post->increment('shares_count');

        return response()->json(['message' => 'Post sent', 'conversation_id' => $conversation->id]);
    }

    private function getFriendIds(User $user): array
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');

        return $sent->merge($received)->unique()->toArray();
    }
}
