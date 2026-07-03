<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        $conversation->load(['users' => fn ($q) => $q->withPivot('last_read_at')]);

        $messages = $conversation->messages()
            ->with('user')
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->sortBy('created_at')
            ->values();

        $this->markIncomingDeliveredAndRead($conversation);

        $initialMessages = $messages
            ->map(fn ($m) => $this->messagePayload($m, $conversation))
            ->values();

        $otherUser = $conversation->users->where('id', '!=', auth()->id())->first();
        $presence = $this->presencePayload($conversation);

        return view('chat.show', compact('conversation', 'initialMessages', 'otherUser', 'presence'));
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

        $conversation->load(['users' => fn ($q) => $q->withPivot('last_read_at')]);

        $query = $conversation->messages()->with('user:id,name')->latest('created_at');

        if ($request->filled('after_id')) {
            $query->where('id', '>', (int) $request->after_id);
        }

        $messages = $query->limit(50)->get()->sortBy('created_at')->values();

        if ($messages->isNotEmpty()) {
            $this->markIncomingDeliveredAndReadThrottled($conversation);
            $conversation->load(['users' => fn ($q) => $q->withPivot('last_read_at')]);
        }

        $statuses = $this->recentStatuses($conversation, $request);

        return response()->json([
            'messages' => $messages->map(fn ($m) => $this->messagePayload($m, $conversation)),
            'statuses' => $statuses,
            'presence' => $this->presencePayload($conversation),
        ]);
    }

    public function typing(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless(
            $conversation->users()->where('user_id', auth()->id())->exists(),
            403
        );

        $validated = $request->validate([
            'typing' => ['required', 'boolean'],
        ]);

        $conversationId = $conversation->id;
        $userId = auth()->id();
        $typing = $validated['typing'];

        dispatch(function () use ($conversationId, $userId, $typing) {
            try {
                broadcast(new UserTyping($conversationId, $userId, $typing))->toOthers();
            } catch (\Throwable $e) {
                report($e);
            }
        })->afterResponse();

        return response()->json(['ok' => true]);
    }

    public function send(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        abort_unless(
            $conversation->users()->where('user_id', auth()->id())->exists(),
            403
        );

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:'.config('media.max_video_kb')],
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
                $mediaPath = MediaStorage::storeEncrypted($file, 'chat');
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

        $messageId = $message->id;
        $conversationId = $conversation->id;
        $senderId = auth()->id();

        dispatch(function () use ($messageId, $conversationId, $senderId) {
            $message = Message::with('user')->find($messageId);
            if (! $message) {
                return;
            }

            try {
                broadcast(new MessageSent($message))->toOthers();
            } catch (\Throwable $e) {
                report($e);
            }

            $conversation = Conversation::find($conversationId);
            $sender = User::find($senderId);
            if ($conversation && $sender) {
                try {
                    NotificationService::chatMessage($conversation, $sender, $message);
                } catch (\Throwable $e) {
                    report($e);
                }
            }
        })->afterResponse();

        if ($request->expectsJson()) {
            $message->load('user');
            $conversation->load(['users' => fn ($q) => $q->withPivot('last_read_at')]);

            return response()->json([
                'message' => $this->messagePayload($message, $conversation),
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

    private function messagePayload(Message $message, Conversation $conversation): array
    {
        $payload = [
            'id' => $message->id,
            'body' => $message->body,
            'message_type' => $message->message_type ?? 'text',
            'call_status' => $message->call_status,
            'call_is_video' => $message->call_is_video,
            'call_label' => $message->isCall() ? $message->callLabelFor(auth()->id()) : null,
            'media_url' => $message->media_url,
            'media_type' => $message->media_type,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'time' => $message->created_at->timezone(config('app.timezone'))->format('g:i A'),
            'is_sender' => $message->user_id === auth()->id(),
            'status' => null,
        ];

        if ($message->user_id === auth()->id()) {
            $payload['status'] = $this->deliveryStatus($message, $conversation);
        }

        return $payload;
    }

    private function deliveryStatus(Message $message, Conversation $conversation): string
    {
        $other = $conversation->users->where('id', '!=', auth()->id())->first();

        if (! $other) {
            return 'sent';
        }

        $lastRead = $other->pivot?->last_read_at;

        if ($lastRead && $lastRead >= $message->created_at) {
            return 'read';
        }

        if ($message->delivered_at || $this->isUserOnline($other)) {
            return 'delivered';
        }

        return 'sent';
    }

    private function presencePayload(Conversation $conversation): array
    {
        $other = $conversation->users->where('id', '!=', auth()->id())->first();

        if (! $other) {
            return ['online' => false, 'label' => 'Offline'];
        }

        $online = $this->isUserOnline($other);

        return [
            'online' => $online,
            'label' => $online ? 'Online' : ($other->last_seen_at
                ? 'Last seen '.$other->last_seen_at->diffForHumans()
                : 'Offline'),
        ];
    }

    private function isUserOnline(User $user): bool
    {
        return $user->last_seen_at && $user->last_seen_at->gte(now()->subMinutes(5));
    }

    private function markIncomingDeliveredAndRead(Conversation $conversation): void
    {
        Message::where('conversation_id', $conversation->id)
            ->where('user_id', '!=', auth()->id())
            ->whereNull('delivered_at')
            ->update(['delivered_at' => now()]);

        $conversation->users()->updateExistingPivot(auth()->id(), ['last_read_at' => now()]);
    }

    private function markIncomingDeliveredAndReadThrottled(Conversation $conversation): void
    {
        $key = 'chat:read:'.$conversation->id.':'.auth()->id();

        if (Cache::has($key)) {
            return;
        }

        $this->markIncomingDeliveredAndRead($conversation);
        Cache::put($key, true, now()->addSeconds(20));
    }

    private function recentStatuses(Conversation $conversation, Request $request): array
    {
        $key = 'chat:statuses:'.$conversation->id.':'.auth()->id();

        if ($request->filled('after_id') && Cache::has($key)) {
            return [];
        }

        $statuses = $conversation->messages()
            ->where('user_id', auth()->id())
            ->when(
                $request->filled('after_id'),
                fn ($q) => $q->where('id', '>', max(0, (int) $request->after_id - 30)),
                fn ($q) => $q->latest('id')->limit(30)
            )
            ->get()
            ->mapWithKeys(fn ($m) => [$m->id => $this->deliveryStatus($m, $conversation)])
            ->all();

        if ($request->filled('after_id')) {
            Cache::put($key, true, now()->addSeconds(12));
        }

        return $statuses;
    }
}
