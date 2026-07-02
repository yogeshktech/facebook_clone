<?php

namespace App\Http\Controllers;

use App\Events\CallSignalingEvent;
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

        broadcast(new CallSignalingEvent(
            auth()->id(),
            (int) $validated['to_user_id'],
            $validated['type'],
            $validated['data'] ?? []
        ))->toOthers();

        return response()->json(['success' => true]);
    }
}
