<?php

namespace Tests\Feature\Profile;

use App\Models\DiaryEntry;
use App\Models\Episode;
use App\Models\EpisodeWatch;
use App\Models\Rating;
use App\Models\Season;
use App\Models\Title;
use App\Models\User;
use App\Models\WatchedTitle;
use App\Services\ProfileStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_totals_sum_movie_and_episode_runtimes()
    {
        $user = User::factory()->create();

        $movie = Title::factory()->create([
            'runtime' => 120,
            'genres' => [['id' => 18, 'name' => 'Drama']],
            'credits' => ['directors' => [['tmdb_id' => 1, 'name' => 'Steven Spielberg']], 'cast' => []],
        ]);
        WatchedTitle::create(['user_id' => $user->id, 'title_id' => $movie->id]);

        $season = Season::factory()->synced()->create();
        WatchedTitle::create(['user_id' => $user->id, 'title_id' => $season->title_id]);

        $withRuntime = Episode::factory()->create([
            'season_id' => $season->id,
            'title_id' => $season->title_id,
            'episode_number' => 1,
            'runtime' => 50,
        ]);
        // Sin runtime en TMDB → se estima en 40'
        $withoutRuntime = Episode::factory()->create([
            'season_id' => $season->id,
            'title_id' => $season->title_id,
            'episode_number' => 2,
            'runtime' => null,
        ]);

        foreach ([$withRuntime, $withoutRuntime] as $episode) {
            EpisodeWatch::create([
                'user_id' => $user->id,
                'episode_id' => $episode->id,
                'title_id' => $episode->title_id,
                'season_id' => $episode->season_id,
                'watched_at' => now(),
            ]);
        }

        $stats = app(ProfileStatsService::class)->for($user);

        $this->assertSame(1, $stats['totals']['movies']);
        $this->assertSame(1, $stats['totals']['shows']);
        $this->assertSame(2, $stats['totals']['episodes']);
        // 120 (película) + 50 + 40 (episodios) = 210 minutos
        $this->assertSame(210, $stats['totals']['minutes']);
        $this->assertSame(4, $stats['totals']['hours']);

        // El factory de la serie también trae Drama → 2 títulos con ese género
        $this->assertSame([['name' => 'Drama', 'total' => 2]], $stats['topGenres']);
        $this->assertSame('Steven Spielberg', $stats['topPeople'][0]['name']);
    }

    public function test_rating_distribution_and_average()
    {
        $user = User::factory()->create();
        [$a, $b] = Title::factory()->count(2)->create();

        Rating::create(['user_id' => $user->id, 'rateable_type' => 'title', 'rateable_id' => $a->id, 'value' => 8]);
        Rating::create(['user_id' => $user->id, 'rateable_type' => 'title', 'rateable_id' => $b->id, 'value' => 4]);

        $stats = app(ProfileStatsService::class)->for($user);

        $this->assertSame(1, $stats['ratingDistribution']['8']);
        $this->assertSame(1, $stats['ratingDistribution']['4']);
        $this->assertSame(0, $stats['ratingDistribution']['10']);
        // (8 + 4) / 2 votos = 6 → 3.0 estrellas
        $this->assertSame(3.0, $stats['totals']['averageRating']);
    }

    public function test_decades_group_by_release_date_with_own_average()
    {
        $user = User::factory()->create();

        $nineties = Title::factory()->create(['release_date' => '1994-06-01']);
        $modern = Title::factory()->create(['release_date' => '2021-01-15']);

        foreach ([$nineties, $modern] as $title) {
            WatchedTitle::create(['user_id' => $user->id, 'title_id' => $title->id]);
        }

        Rating::create(['user_id' => $user->id, 'rateable_type' => 'title', 'rateable_id' => $nineties->id, 'value' => 10]);

        $stats = app(ProfileStatsService::class)->for($user);

        $decades = collect($stats['decades'])->keyBy('decade');

        $this->assertSame(1, $decades[1990]['total']);
        $this->assertSame(5.0, $decades[1990]['averageRating']);
        $this->assertSame(1, $decades[2020]['total']);
        $this->assertNull($decades[2020]['averageRating']);
    }

    public function test_most_rewatched_only_includes_repeated_logs()
    {
        $user = User::factory()->create();
        $repeated = Title::factory()->create();
        $single = Title::factory()->create();

        DiaryEntry::factory()->count(3)->create(['user_id' => $user->id, 'loggable_id' => $repeated->id]);
        DiaryEntry::factory()->create(['user_id' => $user->id, 'loggable_id' => $single->id]);

        $stats = app(ProfileStatsService::class)->for($user);

        $this->assertCount(1, $stats['mostRewatched']);
        $this->assertSame(3, $stats['mostRewatched'][0]['total']);
        $this->assertSame(4, $stats['totals']['diaryEntries']);
    }

    public function test_per_year_counts_diary_entries()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        DiaryEntry::factory()->count(2)->create(['user_id' => $user->id, 'loggable_id' => $title->id, 'watched_on' => '2025-03-10']);
        DiaryEntry::factory()->create(['user_id' => $user->id, 'loggable_id' => $title->id, 'watched_on' => '2026-01-05']);

        $stats = app(ProfileStatsService::class)->for($user);

        $this->assertSame([
            ['year' => 2025, 'total' => 2],
            ['year' => 2026, 'total' => 1],
        ], $stats['perYear']);
    }

    public function test_stats_tab_renders()
    {
        $user = User::factory()->create();

        $this->get("/u/{$user->username}/stats")->assertInertia(
            fn (Assert $page) => $page->component('profile/Stats')->where('profile.username', $user->username)
        );
    }

    public function test_diary_tab_filters_by_year()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        DiaryEntry::factory()->count(2)->create(['user_id' => $user->id, 'loggable_id' => $title->id, 'watched_on' => '2025-07-01']);
        DiaryEntry::factory()->create(['user_id' => $user->id, 'loggable_id' => $title->id, 'watched_on' => '2026-02-14']);

        $this->get("/u/{$user->username}/diary")->assertInertia(
            fn (Assert $page) => $page
                ->has('entries.data', 3)
                ->where('years', [2026, 2025])
                ->where('year', null)
        );

        $this->get("/u/{$user->username}/diary?year=2025")->assertInertia(
            fn (Assert $page) => $page
                ->has('entries.data', 2)
                ->where('year', 2025)
        );
    }
}
