<?php

namespace Tests\Feature\Catalog;

use App\Models\Title;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TmdbImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);
    }

    public function test_resolver_imports_a_movie_and_redirects_to_its_page()
    {
        Http::fake([
            '*/movie/603*' => Http::response($this->moviePayload()),
        ]);

        $response = $this->get('/resolve/movie/603');

        $title = Title::where('tmdb_id', 603)->first();

        $this->assertNotNull($title);
        $this->assertSame('The Matrix', $title->title);
        $this->assertSame('the-matrix', $title->slug);
        $this->assertSame('Matrix', data_get($title->translations, 'es.title'));
        $this->assertNotEmpty($title->credits['cast']);
        $this->assertSame('Lana Wachowski', $title->credits['directors'][0]['name']);

        $response->assertRedirect(route('films.show', $title, absolute: false));
    }

    public function test_resolver_imports_a_tv_show_with_season_stubs()
    {
        Http::fake([
            '*/tv/1396?*' => Http::response($this->tvPayload()),
        ]);

        $this->get('/resolve/tv/1396');

        $title = Title::where('tmdb_id', 1396)->first();

        $this->assertNotNull($title);
        $this->assertTrue($title->isTv());
        $this->assertSame('Breaking Bad', $title->title);
        $this->assertCount(2, $title->seasons);
        $this->assertNull($title->seasons->first()->synced_at);
    }

    public function test_visiting_an_imported_title_does_not_call_tmdb()
    {
        Http::fake();

        $title = Title::factory()->create(['slug' => 'fresh-movie']);

        $this->get('/film/fresh-movie')->assertOk();

        Http::assertNothingSent();
    }

    public function test_season_page_lazily_imports_episodes_once()
    {
        Http::fake([
            '*/tv/1396/season/1*' => Http::response($this->seasonPayload()),
        ]);

        $title = Title::factory()->tv()->create(['tmdb_id' => 1396, 'slug' => 'breaking-bad', 'title' => 'Breaking Bad']);
        $title->seasons()->create([
            'tmdb_id' => 3572,
            'season_number' => 1,
            'name' => 'Season 1',
            'episodes_count' => 2,
        ]);

        $this->get('/show/breaking-bad/season/1')->assertOk();

        $this->assertSame(2, $title->episodes()->count());
        $this->assertNotNull($title->seasons()->first()->synced_at);

        // Segunda visita: el snapshot está fresco, no se vuelve a llamar
        $this->get('/show/breaking-bad/season/1')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_overview_is_localized_according_to_locale()
    {
        $title = Title::factory()->create([
            'slug' => 'localized-movie',
            'overview' => 'English overview',
            'translations' => [
                'es' => ['title' => 'Título ES', 'overview' => 'Sinopsis en español'],
                'en' => ['title' => 'English Title', 'overview' => 'English overview'],
            ],
        ]);

        // Default: español
        $this->get('/film/localized-movie')->assertInertia(
            fn (Assert $page) => $page
                ->component('titles/Film')
                ->where('title.overview', 'Sinopsis en español')
                ->where('title.title', 'Título ES')
        );

        $this->put('/settings/locale', ['locale' => 'en']);

        $this->get('/film/localized-movie')->assertInertia(
            fn (Assert $page) => $page->where('title.overview', 'English overview')
        );
    }

    public function test_film_route_404s_for_tv_titles()
    {
        $title = Title::factory()->tv()->create(['slug' => 'a-tv-show']);

        $this->get('/film/a-tv-show')->assertNotFound();
        $this->get('/show/a-tv-show')->assertOk();
    }

    protected function moviePayload(): array
    {
        return [
            'id' => 603,
            'title' => 'The Matrix',
            'original_title' => 'The Matrix',
            'overview' => 'A computer hacker learns the truth.',
            'tagline' => 'Welcome to the Real World',
            'poster_path' => '/poster.jpg',
            'backdrop_path' => '/backdrop.jpg',
            'release_date' => '1999-03-31',
            'runtime' => 136,
            'genres' => [['id' => 28, 'name' => 'Action']],
            'original_language' => 'en',
            'popularity' => 85.5,
            'credits' => [
                'cast' => [
                    ['id' => 6384, 'name' => 'Keanu Reeves', 'character' => 'Neo', 'profile_path' => '/keanu.jpg'],
                ],
                'crew' => [
                    ['id' => 9339, 'name' => 'Lana Wachowski', 'job' => 'Director', 'profile_path' => null],
                    ['id' => 1, 'name' => 'Someone Else', 'job' => 'Producer', 'profile_path' => null],
                ],
            ],
            'translations' => [
                'translations' => [
                    ['iso_639_1' => 'es', 'data' => ['title' => 'Matrix', 'overview' => 'Un hacker descubre la verdad.', 'tagline' => '']],
                    ['iso_639_1' => 'en', 'data' => ['title' => 'The Matrix', 'overview' => 'A computer hacker learns the truth.', 'tagline' => 'Welcome to the Real World']],
                    ['iso_639_1' => 'fr', 'data' => ['title' => 'Matrix FR', 'overview' => 'French']],
                ],
            ],
        ];
    }

    protected function tvPayload(): array
    {
        return [
            'id' => 1396,
            'name' => 'Breaking Bad',
            'original_name' => 'Breaking Bad',
            'overview' => 'A chemistry teacher turns to crime.',
            'tagline' => 'All Hail the King',
            'poster_path' => '/bb-poster.jpg',
            'backdrop_path' => '/bb-backdrop.jpg',
            'first_air_date' => '2008-01-20',
            'last_air_date' => '2013-09-29',
            'status' => 'Ended',
            'number_of_seasons' => 2,
            'number_of_episodes' => 20,
            'genres' => [['id' => 18, 'name' => 'Drama']],
            'original_language' => 'en',
            'popularity' => 300.1,
            'created_by' => [
                ['id' => 66633, 'name' => 'Vince Gilligan', 'profile_path' => '/vince.jpg'],
            ],
            'aggregate_credits' => [
                'cast' => [
                    ['id' => 17419, 'name' => 'Bryan Cranston', 'roles' => [['character' => 'Walter White']], 'profile_path' => '/bryan.jpg'],
                ],
            ],
            'seasons' => [
                ['id' => 3572, 'season_number' => 1, 'name' => 'Season 1', 'overview' => '', 'poster_path' => '/s1.jpg', 'air_date' => '2008-01-20', 'episode_count' => 7],
                ['id' => 3573, 'season_number' => 2, 'name' => 'Season 2', 'overview' => '', 'poster_path' => '/s2.jpg', 'air_date' => '2009-03-08', 'episode_count' => 13],
            ],
            'translations' => ['translations' => []],
        ];
    }

    protected function seasonPayload(): array
    {
        return [
            'id' => 3572,
            'name' => 'Season 1',
            'overview' => 'The first season.',
            'poster_path' => '/s1.jpg',
            'air_date' => '2008-01-20',
            'episodes' => [
                [
                    'id' => 62085,
                    'episode_number' => 1,
                    'name' => 'Pilot',
                    'overview' => 'Walter White turns 50.',
                    'still_path' => '/pilot.jpg',
                    'air_date' => '2008-01-20',
                    'runtime' => 58,
                ],
                [
                    'id' => 62086,
                    'episode_number' => 2,
                    'name' => "Cat's in the Bag...",
                    'overview' => 'Walt and Jesse clean up.',
                    'still_path' => '/e2.jpg',
                    'air_date' => '2008-01-27',
                    'runtime' => 48,
                ],
            ],
            'translations' => ['translations' => []],
        ];
    }
}
