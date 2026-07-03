<?php

namespace App\Http\Controllers;

use App\Events\CallSignalingEvent;
use App\Models\Conversation;
use App\Models\User;
use App\Services\CallHistoryService;
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

            return response()->json(['ok' => true]);
        }

        return response()->json([
            'ok' => false,
            'message' => 'Reverb is not running on this server. Run: sudo supervisorctl start newbook-reverb (or php artisan reverb:start)',
        ], 503);
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

    public function signal(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to_user_id' => ['required', 'integer', 'exists:users,id'],
            'type' => ['required', 'string', 'in:offer,answer,candidate,hangup,decline'],
            'data' => ['nullable', 'array'],
        ]);

        $target = User::findOrFail($validated['to_user_id']);

        if (! $this->canSignalTo($target)) {
            return response()->json([
                'message' => 'You can only call friends you have chatted with.',
            ], 403);
        }

        if (! $this->reverbReachable()) {
            return response()->json([
                'success' => false,
                'message' => 'Reverb is offline. On the server: sudo supervisorctl start newbook-reverb',
            ], 503);
        }

        $data = $validated['data'] ?? [];
        if (isset($data['sdp']) && is_string($data['sdp'])) {
            $data['sdp'] = $this->normalizeSdp($data['sdp']);
        }

        $broadcastSuccess = true;
        try {
            broadcast(new CallSignalingEvent(
                auth()->id(),
                (int) $validated['to_user_id'],
                $validated['type'],
                $data
            ))->toOthers();
        } catch (\Throwable $e) {
            $broadcastSuccess = false;
            logger()->error("Call signaling broadcast failed: " . $e->getMessage());
        }

        try {
            app(CallHistoryService::class)->recordFromSignal(
                auth()->id(),
                (int) $validated['to_user_id'],
                $validated['type'],
                $data
            );
        } catch (\Throwable $e) {
            logger()->error("Failed to record call history: " . $e->getMessage());
        }

        if (! $broadcastSuccess) {
            return response()->json([
                'success' => false,
                'message' => 'Could not reach call server (Reverb broadcast failed). Check REVERB_BROADCAST_HOST=127.0.0.1 and supervisor newbook-reverb.',
            ], 503);
        }

        return response()->json(['success' => true]);
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

    private function reverbReachable(): bool
    {
        $host = config('broadcasting.connections.reverb.options.host', '127.0.0.1');
        $port = (int) config('broadcasting.connections.reverb.options.port', 8080);
        $socket = @fsockopen($host, $port, $errno, $errstr, 2);

        if (! $socket) {
            return false;
        }

        fclose($socket);

        return true;
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
