<?php

namespace App\Services;

use App\Events\MessageSent;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

class CallHistoryService
{
    public function recordFromSignal(int $fromUserId, int $toUserId, string $type, array $data = []): ?Message
    {
        if (! in_array($type, ['hangup', 'decline'], true)) {
            return null;
        }

        $callId = $data['call_id'] ?? null;
        $isVideo = (bool) ($data['is_video'] ?? $data['isVideo'] ?? false);

        if ($type === 'hangup') {
            if (! empty($data['was_answered'])) {
                return null;
            }

            $callerId = (int) ($data['caller_id'] ?? $fromUserId);
            $calleeId = $callerId === $fromUserId ? $toUserId : $fromUserId;

            return $this->record($callerId, $calleeId, 'unanswered', $isVideo, $callId);
        }

        $callerId = (int) ($data['caller_id'] ?? $toUserId);
        $calleeId = $fromUserId;
        $reason = (string) ($data['reason'] ?? 'declined');
        $status = $reason === 'missed' ? 'unanswered' : 'declined';

        return $this->record($callerId, $calleeId, $status, $isVideo, $callId);
    }

    public function record(
        int $callerId,
        int $calleeId,
        string $status,
        bool $isVideo,
        ?string $callId = null,
    ): ?Message {
        if ($callId && ! Cache::add("call_history:{$callId}", 1, now()->addMinutes(10))) {
            return null;
        }

        if (! in_array($status, ['unanswered', 'declined'], true)) {
            return null;
        }

        $conversation = $this->resolveConversation($callerId, $calleeId);
        if (! $conversation) {
            return null;
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id' => $callerId,
            'body' => '',
            'message_type' => 'call',
            'call_status' => $status,
            'call_is_video' => $isVideo,
        ]);

        $conversation->touch();
        broadcast(new MessageSent($message));

        return $message;
    }

    private function resolveConversation(int $userId1, int $userId2): ?Conversation
    {
        $conversation = Conversation::findBetweenUsers($userId1, $userId2);
        if ($conversation) {
            return $conversation;
        }

        $user1 = User::find($userId1);
        $user2 = User::find($userId2);

        if (! $user1 || ! $user2 || ! $user1->isFriendsWith($user2)) {
            return null;
        }

        $conversation = Conversation::create(['is_group' => false]);
        $conversation->users()->attach([$userId1, $userId2]);

        return $conversation;
    }
}
