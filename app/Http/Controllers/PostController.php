<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Comment;
use App\Models\Conversation;
use App\Models\Like;
use App\Models\Message;
use App\Models\Post;
use App\Models\User;
use App\Notifications\PostInteractionNotification;
use App\Services\NotificationService;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PostController extends Controller
{
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:5000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm', 'max:51200'],
            'group_id' => ['nullable', 'integer', 'exists:groups,id'],
            'page_id' => ['nullable', 'integer', 'exists:pages,id'],
        ]);

        $type = 'text';
        $mediaPath = null;

        if ($request->hasFile('media')) {
            try {
                $file = $request->file('media');
                $type = MediaStorage::mediaType($file);
                $mediaPath = MediaStorage::store($file, 'posts');
            } catch (\Throwable $e) {
                return $this->storeResponse($request, false, 'Media upload failed: '.$e->getMessage());
            }
        }

        $content = trim($validated['content'] ?? '');

        if ($content === '' && ! $mediaPath) {
            return $this->storeResponse($request, false, 'Post must have text or a photo/video.');
        }

        try {
            Post::create([
                'user_id' => $request->user()->id,
                'content' => $content !== '' ? $content : null,
                'type' => $type,
                'media_path' => $mediaPath,
                'group_id' => $validated['group_id'] ?? null,
                'page_id' => $validated['page_id'] ?? null,
            ]);
        } catch (\Throwable $e) {
            if ($mediaPath) {
                MediaStorage::delete($mediaPath);
            }

            return $this->storeResponse($request, false, 'Post save failed: '.$e->getMessage());
        }

        return $this->storeResponse($request, true, 'Post created successfully!');
    }

    private function storeResponse(Request $request, bool $success, string $message): RedirectResponse|JsonResponse
    {
        if ($request->expectsJson()) {
            if ($success) {
                return response()->json(['success' => true, 'message' => $message]);
            }

            return response()->json(['success' => false, 'message' => $message], 422);
        }

        return back()
            ->withInput()
            ->with($success ? 'success' : 'error', $message);
    }

    public function destroy(Post $post): RedirectResponse
    {
        abort_unless($post->user_id === auth()->id(), 403);

        if ($post->media_path) {
            MediaStorage::delete($post->media_path);
        }

        $post->delete();

        return back()->with('success', 'Post deleted.');
    }

    public function like(Post $post): RedirectResponse
    {
        $existing = Like::where('user_id', auth()->id())
            ->where('likeable_id', $post->id)
            ->where('likeable_type', Post::class)
            ->first();

        if ($existing) {
            $existing->delete();

            return back();
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

        return back();
    }

    public function comment(Request $request, Post $post): RedirectResponse
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

        Comment::create([
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

        return back();
    }

    public function share(Post $post): RedirectResponse
    {
        Post::create([
            'user_id' => auth()->id(),
            'shared_post_id' => $post->id,
            'content' => null,
            'type' => 'text',
        ]);

        $post->increment('shares_count');

        if ($post->user_id !== auth()->id()) {
            NotificationService::send(
                $post->user,
                new PostInteractionNotification(auth()->user(), $post, 'share')
            );
        }

        return back()->with('success', 'Post shared to your timeline!');
    }

    public function sendToFriend(Post $post, User $user): RedirectResponse
    {
        abort_unless(auth()->user()->isFriendsWith($user), 403);

        $conversation = Conversation::findBetweenUsers(auth()->id(), $user->id);

        if (! $conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->users()->attach([auth()->id(), $user->id]);
        }

        $postUrl = route('feed.index').'#post-'.$post->id;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'body' => auth()->user()->name.' shared a post with you: '.$postUrl,
        ]);

        $conversation->touch();
        broadcast(new MessageSent($message))->toOthers();

        $post->increment('shares_count');

        return back()->with('success', 'Post sent to '.$user->name.' in Messenger.');
    }
}
