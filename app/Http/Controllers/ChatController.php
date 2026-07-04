<?php

namespace App\Http\Controllers;

use App\Events\MessageSent;
use App\Events\MessageUpdated;
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
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $conversations = $user
            ->conversations()
            ->wherePivotNull('hidden_at')
            ->with(['users', 'latestMessage.user'])
            ->latest('updated_at')
            ->get();

        $friendIds = $this->getFriendIds($user);
        $friends = User::whereIn('id', $friendIds)->orderBy('name')->get();

        return view('chat.index', compact('conversations', 'friends'));
    }

    public function show(Conversation $conversation): View
    {
        abort_unless($this->isMember($conversation), 403);

        $this->unhideConversation($conversation, auth()->id());

        $conversation->load(['users' => fn ($q) => $q->withPivot(['last_read_at', 'hidden_at', 'role'])]);

        $messages = $this->visibleMessagesQuery($conversation)
            ->with(['user', 'replyTo.user'])
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->sortBy('created_at')
            ->values();

        $this->markIncomingDeliveredAndRead($conversation);

        $initialMessages = $messages
            ->map(fn ($m) => $this->messagePayload($m, $conversation))
            ->values();

        $otherUser = $conversation->isGroup()
            ? null
            : $conversation->users->where('id', '!=', auth()->id())->first();

        $presence = $this->presencePayload($conversation);
        $friends = User::whereIn('id', $this->getFriendIds(auth()->user()))
            ->whereNotIn('id', $conversation->users->pluck('id'))
            ->orderBy('name')
            ->get();

        $chatConfig = [
            'edit_window_minutes' => (int) config('chat.edit_window_minutes', 15),
            'delete_for_everyone_minutes' => (int) config('chat.delete_for_everyone_minutes', 60),
            'is_group' => $conversation->isGroup(),
            'can_manage_group' => $conversation->isGroup() && $conversation->isAdmin(auth()->id()),
        ];

        return view('chat.show', compact(
            'conversation',
            'initialMessages',
            'otherUser',
            'presence',
            'friends',
            'chatConfig'
        ));
    }

    public function start(User $user): RedirectResponse
    {
        abort_if($user->id === auth()->id(), 400);

        $conversation = Conversation::findBetweenUsers(auth()->id(), $user->id);

        if (! $conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->users()->attach([
                auth()->id() => ['role' => 'member'],
                $user->id => ['role' => 'member'],
            ]);
        } else {
            $this->unhideConversation($conversation, auth()->id());
        }

        return redirect()->route('chat.show', $conversation);
    }

    public function createGroup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $friendIds = $this->getFriendIds($request->user());
        $memberIds = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id !== auth()->id() && in_array($id, $friendIds, true))
            ->unique()
            ->values();

        if ($memberIds->isEmpty()) {
            return back()->with('error', 'Select at least one friend for the group.');
        }

        $conversation = Conversation::create([
            'name' => $validated['name'],
            'is_group' => true,
        ]);

        $attach = [auth()->id() => ['role' => 'admin']];
        foreach ($memberIds as $id) {
            $attach[$id] = ['role' => 'member'];
        }
        $conversation->users()->attach($attach);

        return redirect()->route('chat.show', $conversation)
            ->with('success', 'Group created.');
    }

    public function addMembers(Request $request, Conversation $conversation): RedirectResponse|JsonResponse
    {
        abort_unless($this->isMember($conversation) && $conversation->isGroup(), 403);
        abort_unless($conversation->isAdmin(auth()->id()), 403);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $friendIds = $this->getFriendIds($request->user());
        $existing = $conversation->users()->pluck('users.id')->all();

        $toAdd = collect($validated['user_ids'])
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id !== auth()->id()
                && in_array($id, $friendIds, true)
                && ! in_array($id, $existing, true))
            ->unique()
            ->values();

        if ($toAdd->isEmpty()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'No new friends to add.'], 422);
            }

            return back()->with('error', 'No new friends to add.');
        }

        $attach = [];
        foreach ($toAdd as $id) {
            $attach[$id] = ['role' => 'member'];
        }
        $conversation->users()->attach($attach);
        $conversation->touch();

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'added' => $toAdd->count(),
                'members' => $conversation->users()->get(['users.id', 'users.name'])->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'avatar_url' => $u->avatar_url,
                ]),
            ]);
        }

        return back()->with('success', $toAdd->count().' member(s) added.');
    }

    public function destroyConversation(Conversation $conversation): RedirectResponse
    {
        abort_unless($this->isMember($conversation), 403);

        $conversation->users()->updateExistingPivot(auth()->id(), [
            'hidden_at' => now(),
        ]);

        return redirect()->route('chat.index')->with('success', 'Chat deleted.');
    }

    public function messages(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($this->isMember($conversation), 403);

        $conversation->load(['users' => fn ($q) => $q->withPivot(['last_read_at', 'hidden_at', 'role'])]);

        $query = $this->visibleMessagesQuery($conversation)
            ->with(['user:id,name', 'replyTo.user:id,name'])
            ->latest('created_at');

        if ($request->filled('after_id')) {
            $query->where('id', '>', (int) $request->after_id);
        }

        $messages = $query->limit(50)->get()->sortBy('created_at')->values();

        if ($messages->isNotEmpty()) {
            $this->markIncomingDeliveredAndReadThrottled($conversation);
            $conversation->load(['users' => fn ($q) => $q->withPivot(['last_read_at', 'hidden_at', 'role'])]);
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
        abort_unless($this->isMember($conversation), 403);

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
        abort_unless($this->isMember($conversation), 403);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:'.config('media.max_video_kb')],
            'reply_to_id' => ['nullable', 'integer', 'exists:messages,id'],
        ]);

        if (! $request->filled('body') && ! $request->hasFile('media')) {
            if ($request->wantsJson()) {
                return response()->json(['error' => 'Message or media is required.'], 422);
            }

            return back()->with('error', 'Message or media is required.');
        }

        $replyToId = null;
        if (! empty($validated['reply_to_id'])) {
            $reply = Message::where('id', $validated['reply_to_id'])
                ->where('conversation_id', $conversation->id)
                ->first();
            if ($reply && ! $reply->isDeletedForEveryone()) {
                $replyToId = $reply->id;
            }
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
            'reply_to_id' => $replyToId,
            'body' => $validated['body'] ?? '',
            'media_path' => $mediaPath,
            'media_type' => $mediaType,
        ]);

        $conversation->touch();
        $this->unhideConversationForAll($conversation);

        $messageId = $message->id;
        $conversationId = $conversation->id;
        $senderId = auth()->id();

        dispatch(function () use ($messageId, $conversationId, $senderId) {
            $message = Message::with(['user', 'replyTo.user'])->find($messageId);
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
            $message->load(['user', 'replyTo.user']);
            $conversation->load(['users' => fn ($q) => $q->withPivot(['last_read_at', 'hidden_at', 'role'])]);

            return response()->json([
                'message' => $this->messagePayload($message, $conversation),
            ]);
        }

        return back();
    }

    public function editMessage(Request $request, Message $message): JsonResponse
    {
        abort_unless($this->isMember($message->conversation), 403);
        abort_unless($message->canEditBy(auth()->id()), 403);

        $validated = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message->update([
            'body' => $validated['body'],
            'edited_at' => now(),
        ]);

        $message->load(['user', 'replyTo.user']);

        try {
            broadcast(new MessageUpdated($message, 'edited'))->toOthers();
        } catch (\Throwable $e) {
            report($e);
        }

        $conversation = $message->conversation;
        $conversation->load(['users' => fn ($q) => $q->withPivot(['last_read_at', 'hidden_at', 'role'])]);

        return response()->json([
            'message' => $this->messagePayload($message, $conversation),
        ]);
    }

    public function deleteMessage(Request $request, Message $message): JsonResponse
    {
        abort_unless($this->isMember($message->conversation), 403);

        $validated = $request->validate([
            'scope' => ['required', 'in:me,everyone'],
        ]);

        $conversation = $message->conversation;
        $conversation->load(['users' => fn ($q) => $q->withPivot(['last_read_at', 'hidden_at', 'role'])]);

        if ($validated['scope'] === 'everyone') {
            abort_unless($message->canDeleteForEveryoneBy(auth()->id()), 403);

            $message->update([
                'body' => '',
                'media_path' => null,
                'media_type' => null,
                'deleted_for_everyone_at' => now(),
                'deleted_by' => auth()->id(),
                'edited_at' => null,
            ]);

            $message->load(['user', 'replyTo.user']);

            try {
                broadcast(new MessageUpdated($message, 'deleted_everyone'))->toOthers();
            } catch (\Throwable $e) {
                report($e);
            }

            return response()->json([
                'message' => $this->messagePayload($message->fresh(['user', 'replyTo.user']), $conversation),
            ]);
        }

        // Delete for me only — other users still see it.
        DB::table('message_user_deletes')->updateOrInsert(
            ['message_id' => $message->id, 'user_id' => auth()->id()],
            ['created_at' => now(), 'updated_at' => now()]
        );

        return response()->json([
            'ok' => true,
            'id' => $message->id,
            'scope' => 'me',
        ]);
    }

    private function isMember(Conversation $conversation): bool
    {
        return $conversation->users()->where('user_id', auth()->id())->exists();
    }

    private function unhideConversation(Conversation $conversation, int $userId): void
    {
        $conversation->users()->updateExistingPivot($userId, ['hidden_at' => null]);
    }

    private function unhideConversationForAll(Conversation $conversation): void
    {
        DB::table('conversation_user')
            ->where('conversation_id', $conversation->id)
            ->whereNotNull('hidden_at')
            ->update(['hidden_at' => null, 'updated_at' => now()]);
    }

    private function visibleMessagesQuery(Conversation $conversation)
    {
        $userId = auth()->id();

        return $conversation->messages()
            ->whereDoesntHave('hiddenForUsers', fn ($q) => $q->where('users.id', $userId));
    }

    private function getFriendIds(User $user): array
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');

        return $sent->merge($received)->unique()->toArray();
    }

    private function messagePayload(Message $message, Conversation $conversation): array
    {
        $deleted = $message->isDeletedForEveryone();
        $viewerId = auth()->id();

        $payload = [
            'id' => $message->id,
            'body' => $deleted ? '' : $message->body,
            'message_type' => $message->message_type ?? 'text',
            'call_status' => $message->call_status,
            'call_is_video' => $message->call_is_video,
            'call_label' => $message->isCall() ? $message->callLabelFor($viewerId) : null,
            'media_url' => $message->media_url,
            'media_type' => $deleted ? null : $message->media_type,
            'user_id' => $message->user_id,
            'user_name' => $message->user?->name,
            'time' => $message->created_at->timezone(config('app.timezone'))->format('g:i A'),
            'is_sender' => $message->user_id === $viewerId,
            'status' => null,
            'is_edited' => ! $deleted && $message->edited_at !== null,
            'deleted_for_everyone' => $deleted,
            'can_edit' => $message->canEditBy($viewerId),
            'can_delete_everyone' => $message->canDeleteForEveryoneBy($viewerId),
            'reply_to' => null,
        ];

        if (! $deleted && $message->reply_to_id) {
            $reply = $message->relationLoaded('replyTo') ? $message->replyTo : $message->replyTo()->with('user')->first();
            if ($reply) {
                $payload['reply_to'] = [
                    'id' => $reply->id,
                    'body' => $reply->isDeletedForEveryone()
                        ? 'This message was deleted'
                        : \Illuminate\Support\Str::limit($reply->body ?: ($reply->media_path ? 'Media' : ''), 80),
                    'user_name' => $reply->user?->name,
                    'user_id' => $reply->user_id,
                    'deleted_for_everyone' => $reply->isDeletedForEveryone(),
                ];
            }
        }

        if ($message->user_id === $viewerId && ! $deleted) {
            $payload['status'] = $this->deliveryStatus($message, $conversation);
        }

        return $payload;
    }

    private function deliveryStatus(Message $message, Conversation $conversation): string
    {
        $others = $conversation->users->where('id', '!=', auth()->id());

        if ($others->isEmpty()) {
            return 'sent';
        }

        $allRead = $others->every(function ($other) use ($message) {
            $lastRead = $other->pivot?->last_read_at;

            return $lastRead && $lastRead >= $message->created_at;
        });

        if ($allRead) {
            return 'read';
        }

        $anyDelivered = $message->delivered_at
            || $others->contains(fn ($other) => $this->isUserOnline($other));

        if ($anyDelivered) {
            return 'delivered';
        }

        return 'sent';
    }

    private function presencePayload(Conversation $conversation): array
    {
        if ($conversation->isGroup()) {
            $count = $conversation->users->count();

            return [
                'online' => false,
                'label' => $count.' members',
                'is_group' => true,
                'member_count' => $count,
            ];
        }

        $other = $conversation->users->where('id', '!=', auth()->id())->first();

        if (! $other) {
            return ['online' => false, 'label' => 'Offline', 'is_group' => false];
        }

        $online = $this->isUserOnline($other);

        return [
            'online' => $online,
            'is_group' => false,
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
            ->whereNull('deleted_for_everyone_at')
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
