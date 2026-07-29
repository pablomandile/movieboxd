<?php

namespace App\Services;

use App\Enums\TitleType;
use App\Models\DiaryEntry;
use App\Models\EpisodeWatch;
use App\Models\Rating;
use App\Models\Title;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Estadísticas del perfil. Los agregados que MySQL resuelve bien (sumas,
 * conteos, promedios) se calculan en SQL; los que dependen de columnas JSON
 * (géneros, créditos) se cuentan en PHP sobre el set acotado de títulos
 * vistos por el usuario.
 */
class ProfileStatsService
{
    public function for(User $user): array
    {
        $watchedTitles = $this->watchedTitles($user);
        $userRatings = $this->userTitleRatings($user);

        return [
            'totals' => $this->totals($user, $watchedTitles),
            'perYear' => $this->perYear($user),
            'ratingDistribution' => $this->ratingDistribution($user),
            'topGenres' => $this->topGenres($watchedTitles),
            'topPeople' => $this->topPeople($watchedTitles),
            'decades' => $this->decades($watchedTitles, $userRatings),
            'mostRewatched' => $this->mostRewatched($user),
        ];
    }

    /**
     * Títulos vistos con las columnas necesarias para los agregados en PHP.
     *
     * @return Collection<int, Title>
     */
    protected function watchedTitles(User $user): Collection
    {
        return Title::query()
            ->join('watched_titles', 'watched_titles.title_id', '=', 'titles.id')
            ->where('watched_titles.user_id', $user->id)
            ->select('titles.id', 'titles.type', 'titles.runtime', 'titles.release_date', 'titles.genres', 'titles.credits')
            ->get();
    }

    /** @return Collection<int, int> rating vigente (1-10) indexado por title_id */
    protected function userTitleRatings(User $user): Collection
    {
        return Rating::where('user_id', $user->id)
            ->where('rateable_type', 'title')
            ->pluck('value', 'rateable_id');
    }

    protected function totals(User $user, Collection $watchedTitles): array
    {
        $movieMinutes = (int) $watchedTitles
            ->where('type', TitleType::Movie)
            ->sum(fn (Title $title) => (int) $title->runtime);

        // Duración de los episodios vistos; si TMDB no la trae, se estima en 40'
        $episodeMinutes = (int) EpisodeWatch::query()
            ->where('episode_watches.user_id', $user->id)
            ->join('episodes', 'episodes.id', '=', 'episode_watches.episode_id')
            ->sum(DB::raw('COALESCE(episodes.runtime, 40)'));

        return [
            'movies' => $watchedTitles->where('type', TitleType::Movie)->count(),
            'shows' => $watchedTitles->where('type', TitleType::Tv)->count(),
            'episodes' => EpisodeWatch::where('user_id', $user->id)->count(),
            'diaryEntries' => DiaryEntry::where('user_id', $user->id)->count(),
            'rewatches' => DiaryEntry::where('user_id', $user->id)->where('is_rewatch', true)->count(),
            'minutes' => $movieMinutes + $episodeMinutes,
            'hours' => (int) round(($movieMinutes + $episodeMinutes) / 60),
            'ratings' => Rating::where('user_id', $user->id)->count(),
            'averageRating' => $this->averageRating($user),
        ];
    }

    protected function averageRating(User $user): ?float
    {
        $average = Rating::where('user_id', $user->id)->avg('value');

        return $average === null ? null : round($average / 2, 2);
    }

    /**
     * Registros del diario por año (los últimos 10 años con actividad).
     */
    protected function perYear(User $user): array
    {
        // SUBSTR en vez de YEAR(): portable entre MySQL y el SQLite de los tests
        return DiaryEntry::query()
            ->where('user_id', $user->id)
            ->selectRaw('SUBSTR(watched_on, 1, 4) as year, COUNT(*) as total')
            ->groupBy('year')
            ->orderByDesc('year')
            ->limit(10)
            ->get()
            ->map(fn ($row) => ['year' => (int) $row->year, 'total' => (int) $row->total])
            ->sortBy('year')
            ->values()
            ->all();
    }

    /**
     * Distribución de las calificaciones propias en los 10 buckets de media estrella.
     */
    protected function ratingDistribution(User $user): array
    {
        $buckets = Rating::where('user_id', $user->id)
            ->selectRaw('value, COUNT(*) as total')
            ->groupBy('value')
            ->pluck('total', 'value');

        $distribution = [];

        for ($value = 1; $value <= 10; $value++) {
            $distribution[(string) $value] = (int) ($buckets[$value] ?? 0);
        }

        return $distribution;
    }

    protected function topGenres(Collection $watchedTitles): array
    {
        return $watchedTitles
            ->flatMap(fn (Title $title) => collect($title->genres ?? [])->pluck('name'))
            ->countBy()
            ->sortDesc()
            ->take(8)
            ->map(fn (int $total, string $name) => ['name' => $name, 'total' => $total])
            ->values()
            ->all();
    }

    /**
     * Directores (películas) y creadores (series) más vistos.
     */
    protected function topPeople(Collection $watchedTitles): array
    {
        return $watchedTitles
            ->flatMap(function (Title $title) {
                $credits = $title->credits ?? [];

                return collect($credits['directors'] ?? [])
                    ->merge($credits['creators'] ?? [])
                    ->pluck('name')
                    ->filter();
            })
            ->countBy()
            ->sortDesc()
            ->take(8)
            ->map(fn (int $total, string $name) => ['name' => $name, 'total' => $total])
            ->values()
            ->all();
    }

    /**
     * Décadas más vistas, con el promedio de las calificaciones propias.
     */
    protected function decades(Collection $watchedTitles, Collection $userRatings): array
    {
        return $watchedTitles
            ->filter(fn (Title $title) => $title->release_date !== null)
            ->groupBy(fn (Title $title) => intdiv($title->release_date->year, 10) * 10)
            ->map(function (Collection $titles, int $decade) use ($userRatings) {
                $rated = $titles
                    ->map(fn (Title $title) => $userRatings[$title->id] ?? null)
                    ->filter();

                return [
                    'decade' => $decade,
                    'total' => $titles->count(),
                    'averageRating' => $rated->isEmpty() ? null : round($rated->avg() / 2, 2),
                ];
            })
            ->sortBy('decade')
            ->values()
            ->all();
    }

    /**
     * Títulos con más registros en el diario (los más revisionados).
     */
    protected function mostRewatched(User $user, int $limit = 5): array
    {
        $counts = DiaryEntry::query()
            ->where('user_id', $user->id)
            ->where('loggable_type', 'title')
            ->selectRaw('loggable_id, COUNT(*) as total')
            ->groupBy('loggable_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc('total')
            ->limit($limit)
            ->pluck('total', 'loggable_id');

        if ($counts->isEmpty()) {
            return [];
        }

        return Title::findMany($counts->keys())
            ->map(fn (Title $title) => [
                'title' => $title->localized_title,
                'total' => (int) $counts[$title->id],
                'posterPath' => $title->poster_path,
                'url' => route($title->isMovie() ? 'films.show' : 'shows.show', $title),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();
    }
}
