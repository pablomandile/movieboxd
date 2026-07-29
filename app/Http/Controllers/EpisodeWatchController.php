<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\EpisodeWatch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EpisodeWatchController extends Controller
{
    public function toggle(Request $request, Episode $episode): RedirectResponse
    {
        $keys = [
            'user_id' => $request->user()->id,
            'episode_id' => $episode->id,
        ];

        $existing = EpisodeWatch::where($keys)->first();

        if ($existing === null) {
            EpisodeWatch::create($keys + [
                'title_id' => $episode->title_id,
                'season_id' => $episode->season_id,
                'watched_at' => now(),
            ]);
        } else {
            $existing->delete();
        }

        return back();
    }
}
