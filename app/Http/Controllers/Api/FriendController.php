<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Friendship;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FriendController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $friendIds = $this->getFriendIds($user);

        return response()->json([
            'friends' => User::whereIn('id', $friendIds)->get(),
            'pending' => Friendship::with('user')
                ->where('friend_id', $user->id)
                ->where('status', 'pending')
                ->get(),
            'sent' => Friendship::with('friend')
                ->where('user_id', $user->id)
                ->where('status', 'pending')
                ->get(),
        ]);
    }

    public function send(User $user): JsonResponse
    {
        if (auth()->id() === $user->id) {
            return response()->json(['error' => 'Cannot send to yourself'], 400);
        }

        $friendship = Friendship::firstOrCreate(
            ['user_id' => auth()->id(), 'friend_id' => $user->id],
            ['status' => 'pending']
        );

        NotificationService::friendRequest($user, $friendship);

        return response()->json($friendship);
    }

    public function accept(Friendship $friendship): JsonResponse
    {
        abort_unless($friendship->friend_id === auth()->id(), 403);
        $friendship->update(['status' => 'accepted']);

        return response()->json($friendship);
    }

    public function reject(Friendship $friendship): JsonResponse
    {
        abort_unless($friendship->friend_id === auth()->id(), 403);
        $friendship->update(['status' => 'rejected']);

        return response()->json($friendship);
    }

    public function cancel(Friendship $friendship): JsonResponse
    {
        abort_unless(
            $friendship->user_id === auth()->id() && $friendship->status === 'pending',
            403
        );

        $friendship->delete();

        return response()->json(['message' => 'Request cancelled']);
    }

    public function unfriend(User $user): JsonResponse
    {
        Friendship::where(function ($q) use ($user) {
            $q->where('user_id', auth()->id())->where('friend_id', $user->id);
        })->orWhere(function ($q) use ($user) {
            $q->where('user_id', $user->id)->where('friend_id', auth()->id());
        })->delete();

        return response()->json(['message' => 'Friend removed']);
    }

    private function getFriendIds(User $user): array
    {
        $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
        $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');

        return $sent->merge($received)->unique()->toArray();
    }
}
