<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);
    }

    public function test_search_maps_tmdb_results_to_resolver_links()
    {
        Http::fake([
            '*/search/multi*' => Http::response([
                'page' => 1,
                'total_pages' => 1,
                'results' => [
                    ['media_type' => 'movie', 'id' => 603, 'title' => 'The Matrix', 'poster_path' => '/p.jpg', 'release_date' => '1999-03-31'],
                    ['media_type' => 'tv', 'id' => 1396, 'name' => 'Breaking Bad', 'poster_path' => '/bb.jpg', 'first_air_date' => '2008-01-20'],
                    ['media_type' => 'person', 'id' => 1, 'name' => 'Keanu Reeves'],
                ],
            ]),
        ]);

        $this->get('/search?q=matrix')->assertInertia(
            fn (Assert $page) => $page
                ->component('Search/Index')
                ->where('query', 'matrix')
                ->has('results', 2)
                ->where('results.0.title', 'The Matrix')
                ->where('results.0.url', route('titles.resolve', ['type' => 'movie', 'tmdbId' => 603]))
                ->where('results.1.type', 'tv')
        );
    }

    public function test_empty_query_makes_no_api_call()
    {
        Http::fake();

        $this->get('/search')->assertOk();

        Http::assertNothingSent();
    }
}
