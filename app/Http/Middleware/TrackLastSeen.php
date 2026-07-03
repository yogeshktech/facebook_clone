<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($user = $request->user()) {
            $shouldUpdate = ! $user->last_seen_at
                || $user->last_seen_at->lt(now()->subMinute());

            if ($shouldUpdate) {
                $userId = $user->id;
                dispatch(function () use ($userId) {
                    User::whereKey($userId)->update(['last_seen_at' => now()]);
                })->afterResponse();
            }
        }

        return $response;
    }
}
