<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('components.post-card', function ($view) {
            if (! auth()->check()) {
                $view->with('friends', collect());

                return;
            }

            $user = auth()->user();
            $sent = $user->sentFriendRequests()->where('status', 'accepted')->pluck('friend_id');
            $received = $user->receivedFriendRequests()->where('status', 'accepted')->pluck('user_id');
            $friendIds = $sent->merge($received)->unique();

            $friends = User::whereIn('id', $friendIds)->orderBy('name')->get();
            $view->with('friends', $friends);
        });
    }
}
