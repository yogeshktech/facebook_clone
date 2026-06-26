<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FollowController extends Controller
{
    public function follow(User $user): RedirectResponse
    {
        if ($user->id === auth()->id()) {
            return back()->with('error', 'You cannot follow yourself.');
        }

        if (! auth()->user()->isFollowing($user)) {
            auth()->user()->following()->attach($user->id);
        }

        return back()->with('success', 'Now following '.$user->name);
    }

    public function unfollow(User $user): RedirectResponse
    {
        auth()->user()->following()->detach($user->id);

        return back()->with('success', 'Unfollowed '.$user->name);
    }
}
