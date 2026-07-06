<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\NotificationService;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $conversations = $request->user()
            ->conversations()
            ->with(['users', 'latestMessage.user'])
            ->latest('updated_at')
            ->get();

        return response()->json($conversations);
    }

    public function show(Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->users()->where('user_id', auth()->id())->exists(), 403);

        $messages = $conversation->messages()
            ->with('user')
            ->latest('created_at')
            ->limit(100)
            ->get()
            ->sortBy('created_at')
            ->values();

        return response()->json($messages);
    }

    public function start(User $user): JsonResponse
    {
        $conversation = Conversation::findBetweenUsers(auth()->id(), $user->id);

        if (! $conversation) {
            $conversation = Conversation::create(['is_group' => false]);
            $conversation->users()->attach([auth()->id(), $user->id]);
        }

        return response()->json($conversation->load('users'));
    }

    public function send(Request $request, Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->users()->where('user_id', auth()->id())->exists(), 403);

        $validated = $request->validate([
            'body' => ['nullable', 'string', 'max:5000'],
            'media' => ['nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,mp4,webm,mov', 'max:'.config('media.max_video_kb')],
        ]);

        $mediaPath = null;
        $mediaType = null;

        if ($request->hasFile('media')) {
            $file = $request->file('media');
            $mediaType = MediaStorage::mediaType($file);
            $mediaPath = MediaStorage::storeEncrypted($file, 'chat');
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

        return response()->json($message->load('user'), 201);
    }

    public function createGroup(Request $request): JsonResponse
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
            return response()->json(['message' => 'Select at least one friend'], 422);
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

        return response()->json($conversation->load('users'), 201);
    }

    public function destroy(Conversation $conversation): JsonResponse
    {
        abort_unless($conversation->users()->where('user_id', auth()->id())->exists(), 403);

        $conversation->users()->updateExistingPivot(auth()->id(), [
            'hidden_at' => now(),
        ]);

        return response()->json(['message' => 'Chat deleted']);
    }

    private function getFriendIds(User $user): array
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');

        return $sent->merge($received)->unique()->toArray();
    }
}
