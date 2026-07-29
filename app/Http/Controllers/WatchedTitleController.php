<?php

namespace App\Http\Controllers;

use App\Models\Title;
use App\Models\WatchedTitle;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WatchedTitleController extends Controller
{
    public function toggle(Request $request, Title $title): RedirectResponse
    {
        $user = $request->user();

        $existing = WatchedTitle::where('user_id', $user->id)
            ->where('title_id', $title->id)
            ->first();

        if ($existing === null) {
            WatchedTitle::create(['user_id' => $user->id, 'title_id' => $title->id]);

            return back();
        }

        // Como en Letterboxd: no se puede des-marcar mientras existan logs del título
        if ($title->diaryEntries()->where('user_id', $user->id)->exists()) {
            return back()->withErrors(['watched' => __('app.unwatch_blocked')]);
        }

        $existing->delete();

        return back();
    }
}
