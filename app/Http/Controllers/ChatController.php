<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = $user
            ->conversations()
            ->with(['users', 'latestMessage.user'])
            ->latest('updated_at')
            ->get();

        $friendIds = $this->getFriendIds($user);
        $friends = User::whereIn('id', $friendIds)->orderBy('name')->get();

        return view('chat.index', compact('conversations', 'friends'));
    }

    public function show(Conversation $conversation): View
    {
        abort_unless(
            $conversation->users()->where('user_id', auth()->id())->exists(),
            403
        );

        $messages = $conversation->messages()
            ->with('user')
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->sortBy('created_at')
            ->values();

        $conversation->users()->updateExistingPivot(auth()->id(), ['last_read_at' => now()]);

        $initialMessages = $messages->map(function ($m) {
            return [
                'id' => $m->id,
                'body' => $m->body,
                'media_url' => $m->media_url,
                'media_type' => $m->media_type,
                'user_id' => $m->user_id,
                'time' => $m->created_at->format('H:i'),
                'is_sender' => $m->user_id === auth()->id(),
            ];
        })->values();

        return view('chat.show', compact('conversation', 'messages', 'initialMessages'));
    }

    public function start(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 400);

        $conversation = Conversation::findBetweenUsers(auth()->id(), $user->id);

        if (! $conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->users()->attach([auth()->id(), $user->id]);
        }

        return redirect()->route('chat.show', $conversation);
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless(
            $conversation->users()->where('user_id', auth()->id())->exists(),
            403
        );

        $query = $conversation->messages()->with('user')->latest('created_at');

        if ($request->filled('after_id')) {
            $query->where('id', '>', (int) $request->after_id);
        }

        $messages = $query->limit(50)->get()->sortBy('created_at')->values();

        return response()->json([
            'messages' => $messages->map(fn ($m) => [
                'id' => $m->id,
                'body' => $m->body,
                'media_url' => $m->media_url,
                'media_type' => $m->media_type,
                'user_id' => $m->user_id,
                'user_name' => $m->user->name,
                'time' => $m->created_at->format('H:i'),
                'is_sender' => $m->user_id === auth()->id(),
            ]),
        ]);
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        abort_unless(
            $conversation->users()->where('user_id', auth()->id())->exists(),
            403
        );

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm', 'max:5120'],
        ]);

        if (! $request->filled('body') && ! $request->hasFile('media')) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Message or media is required.'], 422);
            }

            return back()->with('error', 'Message or media is required.');
        }

        $mediaPath = null;
        $mediaType = null;

        if ($request->hasFile('media')) {
            try {
                $file = $request->file('media');
                $mediaType = MediaStorage::mediaType($file);
                $mediaPath = MediaStorage::store($file, 'chat');
            } catch (\Throwable $e) {
                if ($request->expectsJson()) {
                    return response()->json(['error' => 'Failed to upload media. '.$e->getMessage()], 422);
                }

                return back()->with('error', 'Failed to upload media.');
            }
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => auth()->id(),
            'body' => $validated['body'] ?? '',
            'media_path' => $mediaPath,
            'media_type' => $mediaType,
        ]);

        $conversation->touch();
        broadcast(new MessageSent($message))->toOthers();

        if ($request->expectsJson()) {
            $message->refresh();

            return response()->json([
                'message' => [
                    'id' => $message->id,
                    'body' => $message->body,
                    'media_url' => $message->media_url,
                    'media_type' => $message->media_type,
                    'user_id' => $message->user_id,
                ],
            ]);
        }

        return back();
    }

    private function getFriendIds(User $user): array
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');

        return $sent->merge($received)->unique()->toArray();
    }
}
