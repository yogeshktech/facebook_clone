<?php

namespace App\Http\Controllers;

use App\Events\CallSignalingEvent;
use App\Models\Conversation;
use App\Models\User;
use App\Services\CallHistoryService;
use App\Services\CallInboxService;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallSignalingController extends Controller
{
    public function health(): JsonResponse
    {
        $key = config('broadcasting.connections.reverb.key');
        if (! $key) {
            return response()->json([
                'ok' => false,
                'message' => 'REVERB_APP_KEY is missing. Set it in .env and run: php artisan config:clear',
            ], 503);
        }

        $host = config('broadcasting.connections.reverb.options.host', '127.0.0.1');
        $port = (int) config('broadcasting.connections.reverb.options.port', 8080);

        $socket = @fsockopen($host, $port, $errno, $errstr, 2);

        if ($socket) {
            fclose($socket);

            return response()->json(['ok' => true, 'reverb' => true]);
        }

        // Inbox polling still works without Reverb.
        return response()->json([
            'ok' => true,
            'reverb' => false,
            'message' => 'Realtime server offline — calls use polling fallback.',
        ]);
    }

    public function presence(User $user): JsonResponse
    {
        abort_if($user->id === auth()->id(), 400);

        if (! $this->canSignalTo($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $online = $user->last_seen_at && $user->last_seen_at->gte(now()->subMinutes(5));

        return response()->json([
            'online' => $online,
            'label' => $online ? 'Online' : ($user->last_seen_at
                ? 'Last seen '.$user->last_seen_at->diffForHumans()
                : 'Offline'),
        ]);
    }

    public function inbox(Request $request): JsonResponse
    {
        $after = $request->filled('after') ? (float) $request->query('after') : null;
        $userId = (int) auth()->id();

        // Release session lock so concurrent signal POSTs are not blocked during polling.
        $this->releaseSessionLock($request);

        $signals = app(CallInboxService::class)->pull($userId, $after);

        return response()->json([
            'signals' => $signals,
            'server_time' => microtime(true),
        ]);
    }

    public function signal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'string', 'in:offer,answer,candidate,hangup,decline,media_state'],
            'data' => ['nullable', 'array'],
        ]);

        $fromUser = $request->user();
        $toUserId = (int) $validated['to_user_id'];
        $target = User::findOrFail($toUserId);

        if (! $this->canSignalTo($target)) {
            return response()->json([
                'message' => 'You can only call friends you have chatted with.',
            ], 403);
        }

        // Auth + validation done — free session so inbox polls / other candidates can proceed.
        $this->releaseSessionLock($request);

        $data = $validated['data'] ?? [];
        if (isset($data['sdp']) && is_string($data['sdp'])) {
            $data['sdp'] = $this->normalizeSdp($data['sdp']);
        }

        $type = $validated['type'];

        $payload = [
            'from_user' => [
                'id' => $fromUser->id,
                'name' => $fromUser->name,
                'avatar_url' => $fromUser->avatar_url,
            ],
            'type' => $type,
            'data' => $data,
        ];

        // Always persist to inbox so receiver gets the call even if WebSocket is down.
        app(CallInboxService::class)->push($toUserId, $payload);

        $inbox = app(CallInboxService::class);

        // Hangup/decline also notify the sender's other tabs via inbox.
        if (in_array($type, ['hangup', 'decline'], true)) {
            $inbox->push($fromUser->id, $payload);
        }

        // Clean up pending offers when call is answered, declined or hung up
        if ($type === 'answer' || $type === 'decline') {
            $inbox->clearPendingOffers($fromUser->id);
        } elseif ($type === 'hangup') {
            $inbox->clearPendingOffers($toUserId);
        }

        $broadcastSuccess = false;
        try {
            // Do not use toOthers() — signals target the other user's private channel only.
            // Video SDP payloads need Reverb max_message_size >= ~100KB (see config/reverb.php).
            broadcast(new CallSignalingEvent(
                $fromUser->id,
                $toUserId,
                $type,
                $data
            ));
            $broadcastSuccess = true;
        } catch (\Throwable $e) {
            logger()->warning('Call signaling broadcast failed (inbox fallback active): '.$e->getMessage(), [
                'type' => $type,
                'to_user_id' => $toUserId,
                'payload_bytes' => strlen(json_encode($data) ?: ''),
            ]);
        }

        if ($type === 'offer') {
            try {
                NotificationService::incomingCall(
                    $target,
                    $fromUser,
                    (bool) ($data['isVideo'] ?? $data['is_video'] ?? false),
                    (string) ($data['call_id'] ?? ''),
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        try {
            app(CallHistoryService::class)->recordFromSignal(
                $fromUser->id,
                $toUserId,
                $type,
                $data
            );
        } catch (\Throwable $e) {
            logger()->error('Failed to record call history: '.$e->getMessage());
        }

        return response()->json([
            'success' => true,
            'broadcast' => $broadcastSuccess,
        ]);
    }

    private function canSignalTo(User $target): bool
    {
        $user = auth()->user();

        if ($target->id === $user->id) {
            return false;
        }

        if ($user->isFriendsWith($target)) {
            return true;
        }

        return Conversation::findBetweenUsers($user->id, $target->id) !== null;
    }

    /** Prevent file/database session lock from queuing WebRTC signal + inbox requests. */
    private function releaseSessionLock(Request $request): void
    {
        try {
            if ($request->hasSession()) {
                $request->session()->save();
            }
        } catch (\Throwable $e) {
            // non-fatal
        }
    }

    private function normalizeSdp(string $sdp): string
    {
        $sdp = str_replace(["\r\n", "\r"], "\n", $sdp);
        $lines = array_values(array_filter(
            array_map(static fn (string $line) => rtrim($line), explode("\n", $sdp)),
            static fn (string $line) => $line !== ''
        ));

        return implode("\r\n", $lines)."\r\n";
    }
}
