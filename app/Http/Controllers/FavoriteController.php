<?php

namespace App\Http\Controllers;

use App\Models\Favorite;
use App\Models\Title;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    /**
     * Toggle de un título en los 4 favoritos del perfil.
     */
    public function toggle(Request $request, Title $title): RedirectResponse
    {
        $user = $request->user();

        $existing = Favorite::where('user_id', $user->id)->where('title_id', $title->id)->first();

        if ($existing !== null) {
            $existing->delete();

            return back();
        }

        $positions = Favorite::where('user_id', $user->id)->pluck('position');

        if ($positions->count() >= 4) {
            return back()->withErrors(['favorites' => __('app.favorites_full')]);
        }

        $next = collect([1, 2, 3, 4])->first(fn (int $position) => ! $positions->contains($position));

        Favorite::create([
            'user_id' => $user->id,
            'title_id' => $title->id,
            'position' => $next,
        ]);

        return back();
    }
}
