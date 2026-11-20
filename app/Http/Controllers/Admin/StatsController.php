<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Metrics;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Cómo se está usando la app: actividad de los usuarios, lo que cargan por día,
 * y la parte técnica (TMDB, bots frenados, tamaño de la base, cola de jobs).
 */
class StatsController extends Controller
{
    private const DAYS = 30;

    public function __invoke(): Response
    {
        $today = CarbonImmutable::today();
        $start = $today->subDays(self::DAYS - 1);

        return Inertia::render('admin/Stats', [
            'summary' => $this->summary($today),
            'days' => $this->days($start),
            'users' => $this->users($start),
            'technical' => $this->technical($start),
        ]);
    }

    private function summary(CarbonImmutable $today): array
    {
        $activeSince = fn (CarbonImmutable $from) => DB::table('user_activity_days')
            ->where('date', '>=', $from->toDateString())
            ->distinct()
            ->count('user_id');

        return [
            'users' => User::count(),
            'activeToday' => $activeSince($today),
            'active7' => $activeSince($today->subDays(6)),
            'active30' => $activeSince($today->subDays(self::DAYS - 1)),
            'new30' => User::where('created_at', '>=', $today->subDays(self::DAYS - 1))->count(),
        ];
    }

    /**
     * Una fila por día de los últimos 30, con todas las series (los días sin datos van en 0).
     */
    private function days(CarbonImmutable $start): array
    {
        $byCreatedAt = fn (string $table) => DB::table($table)
            ->where('created_at', '>=', $start)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [
            'activeUsers' => DB::table('user_activity_days')
                ->where('date', '>=', $start->toDateString())
                ->selectRaw('date as day, count(*) as total')
                ->groupBy('date')
                ->pluck('total', 'day'),
            'signups' => $byCreatedAt('users'),
            'watched' => $byCreatedAt('watched_titles'),
            'diary' => $byCreatedAt('diary_entries'),
            'reviews' => $byCreatedAt('reviews'),
        ];

        $metrics = DB::table('daily_metrics')
            ->where('date', '>=', $start->toDateString())
            ->get()
            ->groupBy('metric')
            ->map(fn (Collection $rows) => $rows->pluck('total', 'date'));

        $metric = fn (string ...$names) => collect($names)
            ->map(fn (string $name) => $metrics->get($name, collect()))
            ->reduce(function (Collection $carry, Collection $values) {
                foreach ($values as $day => $total) {
                    $carry[$day] = ($carry[$day] ?? 0) + $total;
                }

                return $carry;
            }, collect());

        $series += [
            'tmdbRequests' => $metric(Metrics::TMDB_REQUESTS),
            'imports' => $metric(Metrics::IMPORTED_TITLES, Metrics::IMPORTED_PEOPLE),
            'blocked' => $metric(Metrics::BLOCKED_CRAWLERS, Metrics::BLOCKED_QUOTA),
        ];

        return collect(range(0, self::DAYS - 1))
            ->map(function (int $offset) use ($start, $series) {
                $day = $start->addDays($offset)->toDateString();

                return ['date' => $day] + array_map(
                    fn (Collection $values) => (int) ($values[$day] ?? 0),
                    $series
                );
            })
            ->all();
    }

    private function users(CarbonImmutable $start)
    {
        return User::query()
            ->withCount(['watchedTitles', 'watchlist', 'diaryEntries', 'reviews'])
            ->addSelect([
                'lists_count' => DB::table('lists')->selectRaw('count(*)')->whereColumn('lists.user_id', 'users.id'),
                'active_days' => DB::table('user_activity_days')->selectRaw('count(*)')
                    ->whereColumn('user_activity_days.user_id', 'users.id')
                    ->where('date', '>=', $start->toDateString()),
            ])
            ->orderByRaw('last_seen_at is null, last_seen_at desc')
            ->orderByDesc('id')
            ->paginate(50)
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'profileUrl' => route('profiles.show', $user->username),
                'createdAt' => $user->created_at->toDateString(),
                'lastSeenAt' => $user->last_seen_at?->toIso8601String(),
                'activeDays' => (int) $user->active_days,
                'watched' => $user->watched_titles_count,
                'diary' => $user->diary_entries_count,
                'reviews' => $user->reviews_count,
                'lists' => (int) $user->lists_count,
                'watchlist' => $user->watchlist_count,
                'isBanned' => $user->isBanned(),
            ]);
    }

    private function technical(CarbonImmutable $start): array
    {
        $sum = fn (string $metric) => (int) DB::table('daily_metrics')
            ->where('metric', $metric)
            ->where('date', '>=', $start->toDateString())
            ->sum('total');

        return [
            'catalog' => [
                'titles' => DB::table('titles')->count(),
                'people' => DB::table('people')->count(),
                'episodes' => DB::table('episodes')->count(),
            ],
            'queue' => [
                'pending' => DB::table('jobs')->count(),
                'failed' => DB::table('failed_jobs')->count(),
            ],
            'last30' => [
                'tmdbRequests' => $sum(Metrics::TMDB_REQUESTS),
                'importedTitles' => $sum(Metrics::IMPORTED_TITLES),
                'importedPeople' => $sum(Metrics::IMPORTED_PEOPLE),
                'importedSeasons' => $sum(Metrics::IMPORTED_SEASONS),
                'blockedCrawlers' => $sum(Metrics::BLOCKED_CRAWLERS),
                'blockedQuota' => $sum(Metrics::BLOCKED_QUOTA),
                'prunedTitles' => $sum(Metrics::PRUNED_TITLES),
                'prunedPeople' => $sum(Metrics::PRUNED_PEOPLE),
            ],
            'guestImportsPerDay' => (int) config('services.tmdb.guest_imports_per_day'),
            'database' => $this->databaseSize(),
        ];
    }

    /**
     * Tamaño por tabla según MySQL/MariaDB. Es lo que mira Hostinger para el
     * límite de la base (a fines de 2026 la demo llegó a 3 GB y quedó sin escritura).
     */
    private function databaseSize(): ?array
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return null;
        }

        try {
            $tables = collect(DB::select(
                'SELECT table_name AS name, data_length + index_length AS bytes, table_rows AS approx_rows
                 FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY bytes DESC'
            ))->map(fn ($row) => ['name' => $row->name, 'bytes' => (int) $row->bytes, 'rows' => (int) $row->approx_rows]);
        } catch (Throwable) {
            return null;
        }

        return [
            'totalBytes' => $tables->sum('bytes'),
            'tables' => $tables->take(8)->values()->all(),
        ];
    }
}
