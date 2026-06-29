<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($user = $request->user()) {
            $shouldUpdate = ! $user->last_seen_at
                || $user->last_seen_at->lt(now()->subMinute());

            if ($shouldUpdate) {
                $user->forceFill(['last_seen_at' => now()])->save();
            }
        }

        return $next($request);
    }
}
