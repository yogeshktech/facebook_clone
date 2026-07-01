<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class VideoController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $friendIds = $user->friends()->pluck('users.id')->toArray();
        $followingIds = $user->following()->pluck('users.id')->toArray();
        $feedUserIds = array_unique(array_merge([$user->id], $friendIds, $followingIds));

        $videos = Post::with(['user'])
            ->withCount(['likes', 'allComments as comments_count', 'reelViews as views_count'])
            ->where('type', 'video')
            ->whereIn('user_id', $feedUserIds)
            ->whereNull('group_id')
            ->whereNull('page_id')
            ->latest()
            ->cursorPaginate(5);

        $friends = User::whereIn('id', $friendIds)->orderBy('name')->get();

        return view('videos.index', compact('videos', 'friends'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'media' => ['required', 'file', 'mimes:mp4,webm,mov', 'max:'.config('media.max_video_kb')],
            'content' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $file = $request->file('media');
            $mediaPath = MediaStorage::store($file, 'videos');

            Post::create([
                'user_id' => auth()->id(),
                'content' => trim($validated['content'] ?? '') ?: null,
                'type' => 'video',
                'media_path' => $mediaPath,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Video upload failed: '.$e->getMessage());
        }

        return back()->with('success', 'Video uploaded successfully!');
    }

    public function show(Post $video): View
    {
        $video->load(['user', 'allComments.user', 'likes']);
        return view('videos.show', compact('video'));
    }

    public function like(Post $video): RedirectResponse|JsonResponse
    {
        $user = auth()->user();
        $liked = false;

        if ($video->likes()->where('user_id', $user->id)->exists()) {
            $video->likes()->where('user_id', $user->id)->delete();
        } else {
            $video->likes()->create([
                'user_id' => $user->id,
            ]);
            $liked = true;
        }

        if (request()->expectsJson()) {
            return response()->json([
                'liked' => $liked,
                'likes_count' => $video->likes()->count(),
            ]);
        }

        return back()->with('success', $liked ? 'Video liked.' : 'Video unliked.');
    }

    public function view(Post $video): JsonResponse
    {
        $user = auth()->user();

        if (!$video->reelViews()->where('user_id', $user->id)->exists()) {
            $video->reelViews()->create(['user_id' => $user->id]);
        }

        return response()->json([
            'message' => 'View recorded.',
            'views_count' => $video->reelViews()->count(),
        ]);
    }

    public function comment(Request $request, Post $video): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'content' => ['required', 'string', 'max:500'],
        ]);

        $comment = new Comment([
            'user_id' => auth()->id(),
            'content' => $validated['content'],
        ]);

        $video->comments()->save($comment);

        if ($request->expectsJson()) {
            $comment->load('user');
            return response()->json([
                'success' => true,
                'comments_count' => $video->comments()->count(),
                'comment' => [
                    'id' => $comment->id,
                    'content' => $comment->content,
                    'created_at_human' => 'Just now',
                    'user' => [
                        'name' => $comment->user->name,
                        'avatar_url' => $comment->user->avatar_url,
                        'profile_url' => route('profile.show', $comment->user),
                    ]
                ]
            ]);
        }

        return back()->with('success', 'Comment added.');
    }

    public function deleteComment(Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);
        $comment->delete();
        return back()->with('success', 'Comment deleted.');
    }
}