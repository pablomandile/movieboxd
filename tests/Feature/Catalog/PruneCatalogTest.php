<?php

namespace Tests\Feature\Catalog;

use App\Jobs\RefreshTitle;
use App\Models\Episode;
use App\Models\Person;
use App\Models\Rating;
use App\Models\Season;
use App\Models\Title;
use App\Models\User;
use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PruneCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function old(): array
    {
        return ['created_at' => now()->subDays(60), 'viewed_at' => null];
    }

    public function test_it_deletes_unused_titles_with_their_seasons_and_episodes()
    {
        $episode = Episode::factory()->create();
        $show = $episode->season->title;
        $show->forceFill($this->old())->save();
        $movie = Title::factory()->create($this->old());

        $this->artisan('movieboxd:prune-catalog')->assertSuccessful();

        $this->assertModelMissing($show);
        $this->assertModelMissing($movie);
        $this->assertModelMissing($episode);
        $this->assertSame(0, Season::count());
    }

    public function test_it_keeps_titles_viewed_or_imported_recently()
    {
        $viewed = Title::factory()->create(['created_at' => now()->subDays(90), 'viewed_at' => now()->subDays(3)]);
        $imported = Title::factory()->create(['created_at' => now()->subDays(3)]);
        $stale = Title::factory()->create(['created_at' => now()->subDays(90), 'viewed_at' => now()->subDays(40)]);

        $this->artisan('movieboxd:prune-catalog')->assertSuccessful();

        $this->assertModelExists($viewed);
        $this->assertModelExists($imported);
        $this->assertModelMissing($stale);
    }

    public function test_it_keeps_titles_with_user_data_even_through_an_episode()
    {
        $user = User::factory()->create();

        $inWatchlist = Title::factory()->create($this->old());
        WatchlistItem::create(['user_id' => $user->id, 'title_id' => $inWatchlist->id]);

        $episode = Episode::factory()->create();
        $ratedShow = $episode->season->title;
        $ratedShow->forceFill($this->old())->save();
        Rating::create(['user_id' => $user->id, 'rateable_type' => 'episode', 'rateable_id' => $episode->id, 'value' => 8]);

        $this->artisan('movieboxd:prune-catalog')->assertSuccessful();

        $this->assertModelExists($inWatchlist);
        $this->assertModelExists($ratedShow);
        $this->assertModelExists($episode);
    }

    public function test_it_keeps_the_titles_the_demo_reseeds_from()
    {
        config(['demo.enabled' => true]);

        $demoMovie = Title::factory()->create(['tmdb_id' => 238] + $this->old());
        $other = Title::factory()->create(['tmdb_id' => 999001] + $this->old());

        $this->artisan('movieboxd:prune-catalog')->assertSuccessful();

        $this->assertModelExists($demoMovie);
        $this->assertModelMissing($other);
    }

    public function test_it_deletes_people_not_viewed_recently()
    {
        $stale = Person::factory()->create(['created_at' => now()->subDays(60)]);
        $viewed = Person::factory()->create(['created_at' => now()->subDays(60), 'viewed_at' => now()->subDay()]);

        $this->artisan('movieboxd:prune-catalog')->assertSuccessful();

        $this->assertModelMissing($stale);
        $this->assertModelExists($viewed);
    }

    public function test_dry_run_deletes_nothing()
    {
        $title = Title::factory()->create($this->old());
        $person = Person::factory()->create(['created_at' => now()->subDays(60)]);

        $this->artisan('movieboxd:prune-catalog --dry-run')
            ->expectsOutputToContain('Se borrarían: 1 títulos y 1 personas.')
            ->assertSuccessful();

        $this->assertModelExists($title);
        $this->assertModelExists($person);
    }

    public function test_refresh_stale_skips_titles_nobody_uses()
    {
        $unused = Title::factory()->create(['synced_at' => now()->subDays(40)]);

        Queue::fake();

        $this->artisan('movieboxd:refresh-stale')->assertSuccessful();

        Queue::assertNotPushed(RefreshTitle::class, fn ($job) => $job->title->is($unused));
    }
}
