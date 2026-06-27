<?php

namespace App\Http\Controllers;

use App\Models\DeviceToken;
use App\Models\SocialNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View|JsonResponse
    {
        $notifications = SocialNotification::where('receiver_id', $request->user()->id)
            ->with('sender')
            ->latest()
            ->paginate(20);

        if ($request->expectsJson()) {
            return response()->json(
                $notifications->through(fn (SocialNotification $n) => $this->format($n))
            );
        }

        return view('notifications.index', compact('notifications'));
    }

    public function count(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->unreadCount($request->user()->id),
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        $notifications = SocialNotification::where('receiver_id', $request->user()->id)
            ->where('is_read', false)
            ->with('sender')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (SocialNotification $n) => $this->format($n));

        return response()->json([
            'count' => $this->unreadCount($request->user()->id),
            'notifications' => $notifications,
        ]);
    }

    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $notification = SocialNotification::where('receiver_id', $request->user()->id)
            ->findOrFail($id);

        $notification->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        SocialNotification::where('receiver_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->json(['success' => true]);
    }

    public function storeDeviceToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['nullable', 'string', 'in:web,android,ios'],
        ]);

        DeviceToken::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'token' => $validated['token'],
            ],
            ['platform' => $validated['platform'] ?? 'web']
        );

        return response()->json(['success' => true]);
    }

    private function unreadCount(int $userId): int
    {
        return SocialNotification::where('receiver_id', $userId)
            ->where('is_read', false)
            ->count();
    }

    private function format(SocialNotification $notification): array
    {
        return $notification->toPayload();
    }
}
