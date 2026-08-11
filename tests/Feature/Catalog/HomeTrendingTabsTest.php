<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HomeTrendingTabsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);

        $movie = fn (int $id) => [
            'id' => $id, 'media_type' => 'movie', 'title' => "Movie {$id}",
            'poster_path' => '/m.jpg', 'release_date' => '2026-01-01',
        ];
        $tv = fn (int $id) => [
            'id' => $id, 'media_type' => 'tv', 'name' => "Show {$id}",
            'poster_path' => '/t.jpg', 'first_air_date' => '2026-01-01',
        ];

        Http::fake([
            // 2 páginas por endpoint; la persona debe filtrarse del mixto
            '*/trending/all/week*' => Http::response(['results' => [
                $movie(1), $tv(2), ['id' => 3, 'media_type' => 'person', 'name' => 'Alguien'], $movie(4),
            ]]),
            '*/trending/movie/week*' => Http::response(['results' => array_map($movie, range(10, 24))]),
            '*/trending/tv/week*' => Http::response(['results' => array_map($tv, range(50, 64))]),
        ]);
    }

    public function test_the_home_exposes_the_three_trending_tabs()
    {
        // El fake devuelve la MISMA página para page=1 y page=2: la fusión de
        // páginas duplica cada ítem y el controller debe deduplicar.
        $this->get('/')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Home')
            // mixto: 4 ítems menos la persona = 3 únicos (el duplicado de página no cuenta)
            ->has('trending', 3)
            ->where('trending.0.type', 'movie')
            ->where('trending.1.type', 'tv')
            // por tipo: 15 únicos por página duplicada
            ->has('trendingMovies', 15)
            ->has('trendingShows', 15)
            ->where('trendingMovies.0.type', 'movie')
            ->where('trendingShows.0.type', 'tv')
        );
    }

    public function test_typed_tabs_contain_a_single_type()
    {
        $response = $this->get('/');

        $response->assertInertia(function ($page) {
            $props = $page->toArray()['props'];

            foreach ($props['trendingMovies'] as $card) {
                $this->assertSame('movie', $card['type']);
            }

            foreach ($props['trendingShows'] as $card) {
                $this->assertSame('tv', $card['type']);
            }

            return $page->component('Home');
        });
    }
}
