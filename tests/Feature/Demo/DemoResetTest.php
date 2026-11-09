<?php

namespace Tests\Feature\Demo;

use App\Models\DiaryEntry;
use App\Models\Episode;
use App\Models\Favorite;
use App\Models\Rating;
use App\Models\Review;
use App\Models\Season;
use App\Models\Title;
use App\Models\User;
use App\Models\WatchedTitle;
use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoResetTest extends TestCase
{
    use RefreshDatabase;

    private const DEMO_USERS = ['demo', 'joaco_cine', 'martisosa', 'tomi_series', 'valerios'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo.enabled' => true]);
        Storage::fake('local');
    }

    public function test_reset_removes_real_users_their_files_and_their_counters_and_keeps_the_catalog(): void
    {
        $ajena = Title::factory()->create(['tmdb_id' => 999999]);
        $real = User::factory()->create(['email' => 'real@x.com']);
        WatchedTitle::create(['user_id' => $real->id, 'title_id' => $ajena->id]);
        Rating::create(['user_id' => $real->id, 'rateable_type' => 'title', 'rateable_id' => $ajena->id, 'value' => 8]);
        Storage::disk('local')->put("letterboxd/{$real->id}/export.zip", 'x');
        $this->assertSame(1, $ajena->fresh()->watched_count);

        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertSame(self::DEMO_USERS, User::orderBy('username')->pluck('username')->all());
        $this->assertModelExists($ajena);
        $ajena->refresh();
        $this->assertSame(0, $ajena->watched_count);
        $this->assertSame(0, $ajena->ratings_count);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_seeds_a_coherent_demo_over_the_titles_in_the_catalog(): void
    {
        foreach ([238, 496243, 129, 25376, 530385, 346, 693134, 603, 62] as $tmdb) {
            Title::factory()->create(['tmdb_id' => $tmdb]);
        }
        $chernobyl = Title::factory()->tv()->create(['tmdb_id' => 87108]);
        $temporada = Season::factory()->create(['title_id' => $chernobyl->id, 'season_number' => 1]);
        foreach (range(1, 5) as $n) {
            Episode::factory()->create(['season_id' => $temporada->id, 'episode_number' => $n]);
        }

        $this->artisan('demo:reset')->assertSuccessful();

        $demo = User::where('email', 'demo@pablomandile.com.ar')->firstOrFail();
        $this->assertNotNull($demo->email_verified_at);
        $this->assertSame('user', $demo->role->value);
        $this->assertGreaterThan(0, DiaryEntry::where('user_id', $demo->id)->count());
        $this->assertSame(4, Favorite::where('user_id', $demo->id)->count());
        $this->assertSame(4, $demo->following()->count());
        $this->assertTrue(WatchedTitle::where('user_id', $demo->id)->where('title_id', $chernobyl->id)->exists());

        // Lo pendiente no está visto (346: Los siete samuráis; 693134: Dune 2; 530385: Midsommar).
        $pendientes = WatchlistItem::where('user_id', $demo->id)->pluck('title_id');
        $this->assertCount(3, $pendientes);
        $this->assertFalse(WatchedTitle::where('user_id', $demo->id)->whereIn('title_id', $pendientes)->exists());

        // Los contadores salen de los observers y coinciden con las tablas.
        $parasite = Title::where('tmdb_id', 496243)->first();
        $this->assertSame(WatchedTitle::where('title_id', $parasite->id)->count(), $parasite->watched_count);
        $this->assertSame(Rating::where('rateable_type', 'title')->where('rateable_id', $parasite->id)->count(), $parasite->ratings_count);
        $this->assertSame(Review::where('reviewable_type', 'title')->where('reviewable_id', $parasite->id)->count(), $parasite->reviews_count);

        // Nada en el futuro.
        $this->assertFalse(DiaryEntry::whereDate('watched_on', '>', today())->exists());
        $this->assertFalse(Review::where('created_at', '>', now())->exists());
    }

    public function test_reset_is_repeatable_and_works_with_an_empty_catalog(): void
    {
        $this->artisan('demo:reset')->assertSuccessful();
        $this->artisan('demo:reset')->assertSuccessful();

        $this->assertSame(self::DEMO_USERS, User::orderBy('username')->pluck('username')->all());
    }
}
