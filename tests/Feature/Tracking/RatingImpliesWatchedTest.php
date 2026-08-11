<?php

namespace Tests\Feature\Tracking;

use App\Models\DiaryEntry;
use App\Models\Season;
use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RatingImpliesWatchedTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Title $title;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->title = Title::factory()->create();
    }

    protected function rate(?int $value): void
    {
        $this->actingAs($this->user)->put('/ratings', [
            'rateable_type' => 'title',
            'rateable_id' => $this->title->id,
            'value' => $value,
        ]);
    }

    public function test_rating_marks_as_watched_creates_a_diary_entry_and_leaves_the_watchlist()
    {
        $this->actingAs($this->user)->post("/titles/{$this->title->slug}/watchlist");
        $this->assertDatabaseHas('watchlist_items', ['user_id' => $this->user->id, 'title_id' => $this->title->id]);

        $this->rate(8);

        $this->assertDatabaseHas('watched_titles', ['user_id' => $this->user->id, 'title_id' => $this->title->id]);
        $this->assertDatabaseMissing('watchlist_items', ['user_id' => $this->user->id, 'title_id' => $this->title->id]);

        $entry = DiaryEntry::where('user_id', $this->user->id)->first();
        $this->assertNotNull($entry);
        $this->assertSame(now()->toDateString(), $entry->watched_on->toDateString());
        $this->assertSame(8, $entry->rating);
        $this->assertFalse($entry->is_rewatch);
    }

    public function test_adjusting_the_stars_does_not_duplicate_the_diary_entry()
    {
        $this->rate(6);
        $this->rate(9);

        $this->assertSame(1, DiaryEntry::where('user_id', $this->user->id)->count());
        $this->assertDatabaseHas('ratings', ['user_id' => $this->user->id, 'rateable_id' => $this->title->id, 'value' => 9]);
    }

    public function test_rating_after_logging_does_not_add_another_entry()
    {
        // Ya lo registró con el LogModal
        $this->actingAs($this->user)->post('/log', [
            'loggable_type' => 'title',
            'loggable_id' => $this->title->id,
            'watched_on' => now()->toDateString(),
        ]);

        $this->rate(7);

        $this->assertSame(1, DiaryEntry::where('user_id', $this->user->id)->count());
    }

    public function test_removing_the_rating_keeps_watched_and_the_diary()
    {
        $this->rate(8);
        $this->rate(null);

        $this->assertDatabaseMissing('ratings', ['user_id' => $this->user->id, 'rateable_id' => $this->title->id]);
        $this->assertDatabaseHas('watched_titles', ['user_id' => $this->user->id, 'title_id' => $this->title->id]);
        $this->assertSame(1, DiaryEntry::where('user_id', $this->user->id)->count());
    }

    public function test_rating_a_season_does_not_log_the_title()
    {
        $season = Season::factory()->create();

        $this->actingAs($this->user)->put('/ratings', [
            'rateable_type' => 'season',
            'rateable_id' => $season->id,
            'value' => 8,
        ]);

        $this->assertSame(0, DiaryEntry::where('user_id', $this->user->id)->count());
        $this->assertSame(0, $this->user->watchedTitles()->count());
    }

    public function test_a_watched_title_cannot_enter_the_watchlist()
    {
        $this->actingAs($this->user)->post("/titles/{$this->title->slug}/watched");

        $this->actingAs($this->user)
            ->post("/titles/{$this->title->slug}/watchlist")
            ->assertSessionHasErrors('watchlist');

        $this->assertDatabaseMissing('watchlist_items', ['user_id' => $this->user->id, 'title_id' => $this->title->id]);
    }

    public function test_the_viewer_receives_the_watch_count()
    {
        DiaryEntry::factory()->count(3)->create([
            'user_id' => $this->user->id,
            'loggable_type' => 'title',
            'loggable_id' => $this->title->id,
        ]);

        $this->actingAs($this->user)
            ->get(route($this->title->isMovie() ? 'films.show' : 'shows.show', $this->title))
            ->assertInertia(fn ($page) => $page->where('viewer.watchCount', 3));
    }
}
