<?php

namespace App\Http\Controllers;

use App\Events\CallSignalingEvent;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CallSignalingController extends Controller
{
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

        broadcast(new CallSignalingEvent(
            auth()->id(),
            (int) $validated['to_user_id'],
            $validated['type'],
            $validated['data'] ?? []
        ))->toOthers();

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
}
