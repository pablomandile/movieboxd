<?php

namespace App\Http\Controllers;

use App\Models\Episode;
use App\Models\EpisodeWatch;
use App\Models\Rating;
use App\Models\Season;
use App\Models\Title;
use App\Services\Tmdb\TmdbImportService;
use App\Support\PageMeta;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SeasonController extends Controller
{
    public function show(Request $request, Title $title, int $seasonNumber, TmdbImportService $importer): Response
    {
        abort_unless($title->isTv(), 404);

        $season = $title->seasons()->where('season_number', $seasonNumber)->first() ?? abort(404);

        // Import lazy de episodios: primera visita a la temporada = 1 llamada API
        if ($season->needsEpisodeSync()) {
            $season = $importer->importSeason($title, $seasonNumber);
        }

        $user = $request->user();

        $viewer = $user ? [
            'watchedEpisodeIds' => EpisodeWatch::where('user_id', $user->id)
                ->where('season_id', $season->id)
                ->pluck('episode_id'),
            'rating' => Rating::where('user_id', $user->id)
                ->where('rateable_type', 'season')
                ->where('rateable_id', $season->id)
                ->value('value'),
            'hasLogged' => $season->diaryEntries()->where('user_id', $user->id)->exists(),
        ] : null;

        return Inertia::render('titles/Season', [
            'viewer' => $viewer,
            'reviews' => Inertia::defer(fn () => ReviewController::popularFor($season, $user?->id)),
            'meta' => PageMeta::make(
                "{$title->localized_title} – {$season->localized_title}",
                $season->localized_overview ?: $title->localized_overview,
                $season->poster_path ?? $title->poster_path,
                'video.tv_show'
            ),
            'show' => $this->showProps($title),
            'season' => $this->seasonProps($season),
            'episodes' => $season->episodes->map(fn (Episode $episode) => [
                'id' => $episode->id,
                'number' => $episode->episode_number,
                'code' => $episode->code,
                'name' => $episode->localized_title,
                'overview' => $episode->localized_overview,
                'stillPath' => $episode->still_path,
                'airDate' => $episode->air_date?->toDateString(),
                'runtime' => $episode->runtime,
            ]),
            'seasonNumbers' => $title->seasons->pluck('season_number'),
        ]);
    }

    public static function showProps(Title $title): array
    {
        return [
            'slug' => $title->slug,
            'title' => $title->localized_title,
            'year' => $title->year,
            'posterPath' => $title->poster_path,
            'backdropPath' => $title->backdrop_path,
        ];
    }

    public static function seasonProps(Season $season): array
    {
        return [
            'id' => $season->id,
            'number' => $season->season_number,
            'name' => $season->localized_title,
            'overview' => $season->localized_overview,
            'posterPath' => $season->poster_path,
            'airDate' => $season->air_date?->toDateString(),
            'episodesCount' => $season->episodes_count,
        ];
    }
}
