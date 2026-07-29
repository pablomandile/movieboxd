<?php

namespace Tests\Feature\Tracking;

use App\Models\DiaryEntry;
use App\Models\Episode;
use App\Models\Season;
use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_watched_toggle_creates_and_removes_the_flag()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $this->actingAs($user)->post("/titles/{$title->slug}/watched");
        $this->assertDatabaseHas('watched_titles', ['user_id' => $user->id, 'title_id' => $title->id]);
        $this->assertSame(1, $title->fresh()->watched_count);

        $this->actingAs($user)->post("/titles/{$title->slug}/watched");
        $this->assertDatabaseMissing('watched_titles', ['user_id' => $user->id, 'title_id' => $title->id]);
        $this->assertSame(0, $title->fresh()->watched_count);
    }

    public function test_marking_watched_removes_from_watchlist()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $this->actingAs($user)->post("/titles/{$title->slug}/watchlist");
        $this->assertSame(1, $title->fresh()->watchlist_count);

        $this->actingAs($user)->post("/titles/{$title->slug}/watched");

        $this->assertDatabaseMissing('watchlist_items', ['user_id' => $user->id, 'title_id' => $title->id]);
        $this->assertSame(0, $title->fresh()->watchlist_count);
    }

    public function test_unwatch_is_blocked_while_diary_entries_exist()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $this->actingAs($user)->post('/log', [
            'loggable_type' => 'title',
            'loggable_id' => $title->id,
            'watched_on' => now()->toDateString(),
        ]);

        $this->assertDatabaseHas('watched_titles', ['user_id' => $user->id, 'title_id' => $title->id]);

        $response = $this->actingAs($user)->post("/titles/{$title->slug}/watched");

        $response->assertSessionHasErrors('watched');
        $this->assertDatabaseHas('watched_titles', ['user_id' => $user->id, 'title_id' => $title->id]);
    }

    public function test_ratings_from_two_users_build_the_histogram()
    {
        [$a, $b] = User::factory()->count(2)->create();
        $title = Title::factory()->create();

        // 3.5 estrellas = 7; 5 estrellas = 10
        $this->actingAs($a)->put('/ratings', ['rateable_type' => 'title', 'rateable_id' => $title->id, 'value' => 7]);
        $this->actingAs($b)->put('/ratings', ['rateable_type' => 'title', 'rateable_id' => $title->id, 'value' => 10]);

        $title->refresh();

        $this->assertSame(2, $title->ratings_count);
        $this->assertSame(17, $title->ratings_sum);
        $this->assertSame(1, $title->ratings_histogram['7']);
        $this->assertSame(1, $title->ratings_histogram['10']);
        $this->assertNotNull($title->weighted_rating);

        // Quitar un rating decrementa
        $this->actingAs($a)->put('/ratings', ['rateable_type' => 'title', 'rateable_id' => $title->id, 'value' => null]);

        $title->refresh();
        $this->assertSame(1, $title->ratings_count);
        $this->assertSame(10, $title->ratings_sum);
        $this->assertSame(0, $title->ratings_histogram['7']);
    }

    public function test_log_creates_diary_entry_with_rating_like_and_watched()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $this->actingAs($user)->post('/log', [
            'loggable_type' => 'title',
            'loggable_id' => $title->id,
            'watched_on' => '2026-07-01',
            'rating' => 8,
            'liked' => true,
            'tags' => ['cine', 'con amigos'],
        ]);

        $this->assertDatabaseHas('diary_entries', [
            'user_id' => $user->id,
            'loggable_type' => 'title',
            'loggable_id' => $title->id,
            'rating' => 8,
            'is_rewatch' => false,
        ]);
        $this->assertDatabaseHas('ratings', ['user_id' => $user->id, 'rateable_id' => $title->id, 'value' => 8]);
        $this->assertDatabaseHas('likes', ['user_id' => $user->id, 'likeable_type' => 'title', 'likeable_id' => $title->id]);
        $this->assertDatabaseHas('watched_titles', ['user_id' => $user->id, 'title_id' => $title->id]);

        $title->refresh();
        $this->assertSame(1, $title->likes_count);
        $this->assertSame(1, $title->watched_count);

        // Segundo log del mismo título → rewatch autodetectado (regla 3)
        $this->actingAs($user)->post('/log', [
            'loggable_type' => 'title',
            'loggable_id' => $title->id,
            'watched_on' => '2026-07-15',
            'rating' => 9,
        ]);

        $this->assertDatabaseHas('diary_entries', [
            'user_id' => $user->id,
            'loggable_id' => $title->id,
            'rating' => 9,
            'is_rewatch' => true,
        ]);

        // El rating vigente es el más reciente
        $this->assertDatabaseHas('ratings', ['user_id' => $user->id, 'rateable_id' => $title->id, 'value' => 9]);
        $this->assertSame(1, $title->fresh()->ratings_count);
    }

    public function test_episode_and_season_watch_tracking()
    {
        $user = User::factory()->create();
        $season = Season::factory()->synced()->create(['episodes_count' => 3]);
        $episodes = Episode::factory()
            ->count(3)
            ->sequence(fn ($sequence) => ['episode_number' => $sequence->index + 1])
            ->create(['season_id' => $season->id, 'title_id' => $season->title_id, 'season_number' => 1]);

        // Toggle de un episodio
        $this->actingAs($user)->post("/episodes/{$episodes[0]->id}/watch");
        $this->assertDatabaseHas('episode_watches', ['user_id' => $user->id, 'episode_id' => $episodes[0]->id]);

        // Marcar temporada completa (idempotente con lo ya visto)
        $this->actingAs($user)->post("/seasons/{$season->id}/watch-all");
        $this->assertSame(3, $user->episodeWatches()->where('season_id', $season->id)->count());

        $this->actingAs($user)->post("/seasons/{$season->id}/watch-all");
        $this->assertSame(3, $user->episodeWatches()->where('season_id', $season->id)->count());

        // Desmarcar todo
        $this->actingAs($user)->delete("/seasons/{$season->id}/watch-all");
        $this->assertSame(0, $user->episodeWatches()->count());
    }

    public function test_logging_a_season_marks_its_episodes_watched()
    {
        $user = User::factory()->create();
        $season = Season::factory()->synced()->create(['episodes_count' => 2]);
        Episode::factory()
            ->count(2)
            ->sequence(fn ($sequence) => ['episode_number' => $sequence->index + 1])
            ->create(['season_id' => $season->id, 'title_id' => $season->title_id, 'season_number' => 1]);

        $this->actingAs($user)->post('/log', [
            'loggable_type' => 'season',
            'loggable_id' => $season->id,
            'watched_on' => now()->toDateString(),
            'rating' => 9,
        ]);

        $this->assertSame(2, $user->episodeWatches()->where('season_id', $season->id)->count());
        $this->assertSame(1, $season->fresh()->ratings_count);
    }

    public function test_users_can_only_modify_their_own_diary_entries()
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $entry = DiaryEntry::factory()->create(['user_id' => $owner->id]);

        $this->actingAs($intruder)
            ->put("/diary/{$entry->id}", ['watched_on' => now()->toDateString()])
            ->assertForbidden();

        $this->actingAs($intruder)->delete("/diary/{$entry->id}")->assertForbidden();

        $this->actingAs($owner)->delete("/diary/{$entry->id}")->assertRedirect();
        $this->assertDatabaseMissing('diary_entries', ['id' => $entry->id]);
    }

    public function test_diary_and_watchlist_pages_render()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $this->actingAs($user)->post("/titles/{$title->slug}/watchlist");
        DiaryEntry::factory()->create(['user_id' => $user->id, 'loggable_id' => $title->id]);

        $this->actingAs($user)->get('/diary')->assertOk();
        $this->actingAs($user)->get('/watchlist')->assertOk();
    }

    public function test_reconcile_command_fixes_drifted_counters()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $this->actingAs($user)->put('/ratings', ['rateable_type' => 'title', 'rateable_id' => $title->id, 'value' => 6]);
        $this->actingAs($user)->post("/titles/{$title->slug}/watched");

        // Corromper los contadores a propósito
        $title->forceFill(['ratings_count' => 99, 'ratings_sum' => 99, 'watched_count' => 99])->saveQuietly();

        $this->artisan('movieboxd:reconcile-aggregates')->assertSuccessful();

        $title->refresh();
        $this->assertSame(1, $title->ratings_count);
        $this->assertSame(6, $title->ratings_sum);
        $this->assertSame(1, $title->watched_count);
    }
}
