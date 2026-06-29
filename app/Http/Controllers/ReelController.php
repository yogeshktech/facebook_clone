<?php

namespace App\Http\Controllers;

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
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReelController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $friendIds = $this->getFriendIds($user);
        $followingIds = $user->following()->pluck('users.id')->toArray();
        $feedUserIds = array_unique(array_merge([$user->id], $friendIds, $followingIds));

        $reels = Post::with(['user'])
            ->withCount(['likes', 'allComments as comments_count', 'reelViews as views_count'])
            ->where('type', 'reel')
            ->whereIn('user_id', $feedUserIds)
            ->whereNull('group_id')
            ->whereNull('page_id')
            ->latest()
            ->paginate(10);

        $friends = User::whereIn('id', $friendIds)->orderBy('name')->get();

        return view('reels.index', compact('reels', 'friends'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'media' => ['required', 'file', 'mimes:mp4,webm,mov', 'max:'.config('media.max_video_kb')],
            'content' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $file = $request->file('media');
            $mediaPath = MediaStorage::store($file, 'reels');

            Post::create([
                'user_id' => auth()->id(),
                'content' => trim($validated['content'] ?? '') ?: null,
                'type' => 'reel',
                'media_path' => $mediaPath,
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Reel upload failed: '.$e->getMessage());
        }

        return redirect()->route('reels.index')->with('success', 'Reel posted!');
    }

    public function like(Post $reel): RedirectResponse|JsonResponse
    {
        abort_unless($reel->type === 'reel', 404);

        $existing = Like::where('user_id', auth()->id())
            ->where('likeable_id', $reel->id)
            ->where('likeable_type', Post::class)
            ->first();

        if ($existing) {
            $existing->delete();
            $liked = false;
        } else {
            Like::create([
                'user_id' => auth()->id(),
                'likeable_id' => $reel->id,
                'likeable_type' => Post::class,
            ]);
            $liked = true;

            if ($reel->user_id !== auth()->id()) {
                NotificationService::postInteraction($reel->user, auth()->user(), $reel, 'like');
            }
        }

        if (request()->expectsJson()) {
            return response()->json([
                'liked' => $liked,
                'likes_count' => $reel->likes()->count(),
            ]);
        }

        return back();
    }

    public function comment(Request $request, Post $reel): RedirectResponse|JsonResponse
    {
        abort_unless($reel->type === 'reel', 404);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        Comment::create([
            'user_id' => auth()->id(),
            'post_id' => $reel->id,
            'content' => $validated['content'],
        ]);

        if ($reel->user_id !== auth()->id()) {
            NotificationService::postInteraction($reel->user, auth()->user(), $reel, 'comment');
        }

        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }

        return back();
    }

    public function share(Post $reel): RedirectResponse
    {
        abort_unless($reel->type === 'reel', 404);

        Post::create([
            'user_id' => auth()->id(),
            'shared_post_id' => $reel->id,
            'content' => null,
            'type' => 'text',
        ]);

        $reel->increment('shares_count');

        if ($reel->user_id !== auth()->id()) {
            NotificationService::postInteraction($reel->user, auth()->user(), $reel, 'share');
        }

        return back()->with('success', 'Reel shared to your timeline!');
    }

    public function sendToFriend(Post $reel, User $user): RedirectResponse
    {
        abort_unless($reel->type === 'reel', 404);
        abort_unless(auth()->user()->isFriendsWith($user), 403);

        $conversation = Conversation::findBetweenUsers(auth()->id(), $user->id);

        if (! $conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->users()->attach([auth()->id(), $user->id]);
        }

        $reelUrl = route('reels.index').'#reel-'.$reel->id;

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'body' => auth()->user()->name.' shared a reel with you: '.$reelUrl,
        ]);

        $conversation->touch();
        broadcast(new MessageSent($message))->toOthers();
        NotificationService::chatMessage($conversation, auth()->user(), $message);

        $reel->increment('shares_count');

        return back()->with('success', 'Reel sent to '.$user->name.' in Messenger.');
    }

    public function view(Request $request, Post $reel): JsonResponse
    {
        abort_unless($reel->type === 'reel', 404);

        $reel->recordReelView($request->user()->id);

        return response()->json([
            'views_count' => $reel->reelViews()->count(),
        ]);
    }

    private function getFriendIds(User $user): array
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');

        return $sent->merge($received)->unique()->toArray();
    }
}
