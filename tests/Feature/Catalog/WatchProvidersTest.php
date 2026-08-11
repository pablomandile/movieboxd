<?php

namespace Tests\Feature\Catalog;

use App\Jobs\RefreshTitle;
use App\Models\Setting;
use App\Models\Title;
use App\Services\Tmdb\TmdbImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WatchProvidersTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key', 'services.tmdb.watch_region' => 'AR']);
    }

    /** Payload con la forma real de TMDB (append_to_response=watch/providers). */
    protected function fakeMovie(array $providerResults): void
    {
        Http::fake([
            '*/movie/152601*' => Http::response([
                'id' => 152601,
                'title' => 'Her',
                'original_title' => 'Her',
                'overview' => 'Overview',
                'tagline' => '',
                'poster_path' => '/p.jpg',
                'backdrop_path' => '/b.jpg',
                'release_date' => '2013-12-18',
                'runtime' => 126,
                'genres' => [],
                'original_language' => 'en',
                'popularity' => 10,
                'credits' => ['cast' => [], 'crew' => []],
                'translations' => ['translations' => []],
                'watch/providers' => ['results' => $providerResults],
            ]),
        ]);
    }

    public function test_it_stores_only_the_configured_region()
    {
        $this->fakeMovie([
            'AR' => [
                'link' => 'https://www.themoviedb.org/movie/152601/watch?locale=AR',
                'flatrate' => [
                    ['provider_id' => 339, 'provider_name' => 'MovistarTV', 'logo_path' => '/m.jpg', 'display_priority' => 2],
                    ['provider_id' => 1899, 'provider_name' => 'HBO Max', 'logo_path' => '/h.jpg', 'display_priority' => 1],
                ],
                'rent' => [['provider_id' => 167, 'provider_name' => 'Claro video', 'logo_path' => '/c.jpg', 'display_priority' => 5]],
            ],
            'US' => [
                'link' => 'https://example.com/us',
                'flatrate' => [['provider_id' => 8, 'provider_name' => 'Netflix', 'logo_path' => '/n.jpg', 'display_priority' => 1]],
            ],
        ]);

        $title = app(TmdbImportService::class)->importMovie(152601);
        $providers = $title->fresh()->watch_providers;

        $this->assertSame('AR', $providers['region']);
        $this->assertStringContainsString('locale=AR', $providers['link']);

        // Solo la región configurada: guardar los ~70 países infla el snapshot
        $this->assertSame(['flatrate', 'rent'], array_keys($providers['offers']));
        $this->assertSame('HBO Max', $providers['offers']['flatrate'][0]['name']); // ordenado por display_priority
        $this->assertSame('MovistarTV', $providers['offers']['flatrate'][1]['name']);
        $this->assertSame('Claro video', $providers['offers']['rent'][0]['name']);

        $this->assertStringNotContainsString('Netflix', json_encode($providers));
        $this->assertNotNull($title->fresh()->watch_providers_synced_at);
    }

    public function test_the_region_is_configurable_from_the_admin_settings()
    {
        Setting::put('watch.region', 'US');

        $this->fakeMovie([
            'AR' => ['link' => 'x', 'flatrate' => [['provider_id' => 339, 'provider_name' => 'MovistarTV', 'logo_path' => null, 'display_priority' => 1]]],
            'US' => ['link' => 'y', 'flatrate' => [['provider_id' => 8, 'provider_name' => 'Netflix', 'logo_path' => null, 'display_priority' => 1]]],
        ]);

        $providers = app(TmdbImportService::class)->importMovie(152601)->fresh()->watch_providers;

        $this->assertSame('US', $providers['region']);
        $this->assertSame('Netflix', $providers['offers']['flatrate'][0]['name']);
    }

    public function test_a_title_without_availability_stores_null()
    {
        $this->fakeMovie(['US' => ['link' => 'x', 'flatrate' => []]]);

        $this->assertNull(app(TmdbImportService::class)->importMovie(152601)->fresh()->watch_providers);
    }

    public function test_the_region_entry_without_offers_stores_null()
    {
        // TMDB devuelve el país con link pero sin ninguna oferta
        $this->fakeMovie(['AR' => ['link' => 'https://example.com/ar']]);

        $this->assertNull(app(TmdbImportService::class)->importMovie(152601)->fresh()->watch_providers);
    }

    public function test_the_title_page_exposes_the_providers()
    {
        $title = Title::factory()->create([
            'watch_providers' => [
                'region' => 'AR',
                'link' => 'https://www.themoviedb.org/movie/1/watch?locale=AR',
                'offers' => ['flatrate' => [['id' => 8, 'name' => 'Netflix', 'logo' => '/n.jpg']]],
            ],
        ]);

        $this->get(route('films.show', $title->slug))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('title.watchProviders.region', 'AR')
                ->where('title.watchProviders.offers.flatrate.0.name', 'Netflix')
            );
    }

    public function test_titles_imported_before_this_feature_are_refreshed()
    {
        // watch_providers_synced_at en NULL: sin un whereNull explícito, la
        // comparación `NULL < fecha` los dejaría fuera para siempre
        $title = Title::factory()->create([
            'synced_at' => now(),
            'watch_providers_synced_at' => null,
        ]);

        Queue::fake();

        $this->artisan('movieboxd:refresh-stale')->assertSuccessful();

        Queue::assertPushed(RefreshTitle::class, fn ($job) => $job->title->is($title));
    }

    public function test_stale_availability_is_refreshed_even_if_the_metadata_is_fresh()
    {
        // Metadata al día, disponibilidad vencida: los catálogos rotan más rápido
        $title = Title::factory()->create([
            'synced_at' => now(),
            'watch_providers_synced_at' => now()->subDays(10),
        ]);

        Queue::fake();

        $this->artisan('movieboxd:refresh-stale')->assertSuccessful();

        Queue::assertPushed(
            RefreshTitle::class,
            fn ($job) => $job->title->is($title)
        );
    }
}
