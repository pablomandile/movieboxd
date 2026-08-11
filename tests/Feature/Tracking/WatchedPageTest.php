<?php

namespace Tests\Feature\Tracking;

use App\Models\Title;
use App\Models\User;
use App\Models\WatchedTitle;
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

    public function test_the_most_recent_comes_first()
    {
        $user = User::factory()->create();

        $viejo = Title::factory()->create();
        $medio = Title::factory()->create();
        $nuevo = Title::factory()->create();

        foreach ([[$viejo, '2020-01-01'], [$nuevo, '2026-08-10'], [$medio, '2023-05-05']] as [$title, $date]) {
            WatchedTitle::create([
                'user_id' => $user->id, 'title_id' => $title->id,
                'created_at' => $date, 'updated_at' => $date,
            ]);
        }

        $this->actingAs($user)->get('/watched')->assertInertia(
            fn ($page) => $page->where('titles.data.0.tmdbId', $nuevo->tmdb_id)
                ->where('titles.data.1.tmdbId', $medio->tmdb_id)
                ->where('titles.data.2.tmdbId', $viejo->tmdb_id)
        );
    }

    /**
     * El import de Letterboxd deja cientos de títulos con la misma fecha. Sin un
     * desempate determinístico, LIMIT/OFFSET puede repetir y saltear títulos.
     */
    public function test_pagination_is_stable_when_dates_are_tied()
    {
        $user = User::factory()->create();

        foreach (Title::factory()->count(60)->create() as $title) {
            WatchedTitle::create([
                'user_id' => $user->id, 'title_id' => $title->id,
                'created_at' => '2020-04-05', 'updated_at' => '2020-04-05',
            ]);
        }

        $ids = collect();

        foreach ([1, 2] as $pagina) {
            $response = $this->actingAs($user)->get("/watched?page={$pagina}");
            $props = $response->viewData('page')['props'];
            $ids = $ids->concat(collect($props['titles']['data'])->pluck('tmdbId'));
        }

        $this->assertCount(60, $ids, 'las dos páginas deben cubrir los 60 títulos');
        $this->assertCount(60, $ids->unique(), 'ningún título puede repetirse entre páginas');
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
