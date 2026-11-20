<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Metrics;
use App\Support\Usage;
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

        $demo = (bool) config('demo.enabled');

        return Inertia::render('admin/Stats', [
            'demo' => $demo,
            'summary' => $this->summary($today, $start),
            'days' => $this->days($start),
            'actions' => $this->actions($start),
            // En la demo todos entran con la misma cuenta: el detalle por usuario no dice nada
            'users' => $demo ? null : $this->users($start),
            'technical' => $this->technical($start),
        ]);
    }

    private function summary(CarbonImmutable $today, CarbonImmutable $start): array
    {
        $metric = fn (string $name, CarbonImmutable $from) => (int) DB::table('daily_metrics')
            ->where('metric', $name)
            ->where('date', '>=', $from->toDateString())
            ->sum('total');

        $activeSince = fn (CarbonImmutable $from) => DB::table('user_activity_days')
            ->where('date', '>=', $from->toDateString())
            ->distinct()
            ->count('user_id');

        return [
            'visitsToday' => $metric(Metrics::VISITS, $today),
            'visits30' => $metric(Metrics::VISITS, $start),
            'logins30' => $metric(Metrics::LOGINS, $start),
            'actions30' => (int) DB::table('daily_metrics')
                ->where('metric', 'like', Metrics::ACTIONS_PREFIX.'%')
                ->where('date', '>=', $start->toDateString())
                ->sum('total'),
            'active30' => $activeSince($start),
            'users' => User::count(),
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
            'visits' => $metric(Metrics::VISITS),
            'pageviews' => $metric(Metrics::PAGEVIEWS),
            'logins' => $metric(Metrics::LOGINS),
            'actions' => $metric(...array_map(fn (string $action) => Metrics::ACTIONS_PREFIX.$action, Usage::actionNames())),
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

    /**
     * Cuántas veces se hizo cada cosa en los últimos 30 días, de la más a la menos usada.
     *
     * @return list<array{key: string, total: int}>
     */
    private function actions(CarbonImmutable $start): array
    {
        $totals = DB::table('daily_metrics')
            ->where('metric', 'like', Metrics::ACTIONS_PREFIX.'%')
            ->where('date', '>=', $start->toDateString())
            ->groupBy('metric')
            ->selectRaw('metric, sum(total) as total')
            ->pluck('total', 'metric');

        return collect(Usage::actionNames())
            ->map(fn (string $action) => ['key' => $action, 'total' => (int) ($totals[Metrics::ACTIONS_PREFIX.$action] ?? 0)])
            ->sortByDesc('total')
            ->values()
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
