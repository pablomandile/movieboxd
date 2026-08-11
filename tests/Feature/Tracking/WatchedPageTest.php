<?php

namespace Tests\Feature\Tracking;

use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchedPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_page_requires_a_session()
    {
        $this->get('/watched')->assertRedirect('/login');
    }

    public function test_it_lists_what_the_user_marked_as_watched()
    {
        $user = User::factory()->create();
        $watched = Title::factory()->create();
        $other = Title::factory()->create();

        $this->actingAs($user)->post("/titles/{$watched->slug}/watched");

        $this->actingAs($user)->get('/watched')->assertInertia(
            fn ($page) => $page->component('Watched/Index')
                ->has('titles.data', 1)
                ->where('titles.data.0.tmdbId', $watched->tmdb_id)
                ->where('counts.all', 1)
        );

        $this->assertSame(0, $user->watchedTitles()->where('titles.id', $other->id)->count());
    }

    public function test_a_rated_title_shows_up_with_its_rating()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        // Calificar implica visto: no hace falta marcarlo aparte
        $this->actingAs($user)->put('/ratings', [
            'rateable_type' => 'title',
            'rateable_id' => $title->id,
            'value' => 8,
        ]);

        $this->actingAs($user)->get('/watched')->assertInertia(
            fn ($page) => $page->has('titles.data', 1)
                ->where('titles.data.0.rating', 8)
        );
    }

    public function test_it_filters_by_type_and_counts_both()
    {
        $user = User::factory()->create();
        $movie = Title::factory()->create();
        $show = Title::factory()->tv()->create();

        $this->actingAs($user)->post("/titles/{$movie->slug}/watched");
        $this->actingAs($user)->post("/titles/{$show->slug}/watched");

        $this->actingAs($user)->get('/watched?type=tv')->assertInertia(
            fn ($page) => $page->has('titles.data', 1)
                ->where('titles.data.0.tmdbId', $show->tmdb_id)
                ->where('type', 'tv')
                ->where('counts.all', 2)
                ->where('counts.movie', 1)
                ->where('counts.tv', 1)
        );
    }

    public function test_an_unknown_type_falls_back_to_showing_everything()
    {
        $user = User::factory()->create();
        $title = Title::factory()->create();

        $this->actingAs($user)->post("/titles/{$title->slug}/watched");

        $this->actingAs($user)->get('/watched?type=banana')->assertInertia(
            fn ($page) => $page->has('titles.data', 1)->where('type', null)
        );
    }

    public function test_it_only_shows_your_own_watched_titles()
    {
        $user = User::factory()->create();
        $stranger = User::factory()->create();
        $title = Title::factory()->create();

        $this->actingAs($stranger)->post("/titles/{$title->slug}/watched");

        $this->actingAs($user)->get('/watched')->assertInertia(
            fn ($page) => $page->has('titles.data', 0)->where('counts.all', 0)
        );
    }
}
