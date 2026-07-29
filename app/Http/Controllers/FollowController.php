<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function toggle(Request $request, User $user): RedirectResponse
    {
        $follower = $request->user();

        abort_if($follower->id === $user->id, 422);

        if ($follower->isFollowing($user)) {
            $follower->following()->detach($user->id);
        } else {
            $follower->following()->syncWithoutDetaching([$user->id]);
        }

        return back();
    }
}
