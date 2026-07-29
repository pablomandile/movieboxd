<?php

namespace App\Http\Controllers;

use App\Models\EpisodeWatch;
use App\Models\Season;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SeasonWatchController extends Controller
{
    /**
     * Marca todos los episodios de la temporada como vistos.
     */
    public function store(Request $request, Season $season): RedirectResponse
    {
        $user = $request->user();

        $alreadyWatched = EpisodeWatch::where('user_id', $user->id)
            ->where('season_id', $season->id)
            ->pluck('episode_id');

        $now = now();

        $rows = $season->episodes()
            ->whereNotIn('id', $alreadyWatched)
            ->get()
            ->map(fn ($episode) => [
                'user_id' => $user->id,
                'episode_id' => $episode->id,
                'title_id' => $episode->title_id,
                'season_id' => $episode->season_id,
                'watched_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ])
            ->all();

        if ($rows !== []) {
            EpisodeWatch::insert($rows);
        }

        return back();
    }

    public function destroy(Request $request, Season $season): RedirectResponse
    {
        EpisodeWatch::where('user_id', $request->user()->id)
            ->where('season_id', $season->id)
            ->delete();

        return back();
    }
}
