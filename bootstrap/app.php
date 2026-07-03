<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->statefulApi();
        $middleware->trimStrings(except: [
            'password',
            'password_confirmation',
            'current_password',
            'data.sdp',
        ]);
        $middleware->redirectUsersTo('/feed');
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureAdmin::class,
            'client' => \App\Http\Middleware\EnsureClient::class,
        ]);
        $middleware->web(append: [
            \App\Http\Middleware\TrackLastSeen::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Http\Exceptions\PostTooLargeException $e, $request) {
            $maxMb = config('media.max_video_mb', 100);
            $message = "Video/file is too large. Maximum is {$maxMb}MB. Server must have PHP post_max_size=110M and nginx client_max_body_size=128M. See deploy/README.md";

            if ($request->expectsJson()) {
                return response()->json(['message' => $message], 413);
            }

            return redirect()->back()->with('error', $message);
        });
    })->create();
