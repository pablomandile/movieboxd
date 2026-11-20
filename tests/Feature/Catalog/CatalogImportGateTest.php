<?php

namespace Tests\Feature\Catalog;

use App\Models\Person;
use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CatalogImportGateTest extends TestCase
{
    use RefreshDatabase;

    private const GOOGLEBOT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);
    }

    private function fakeMovies(): void
    {
        Http::fake(fn ($request) => Http::response([
            'id' => (int) preg_replace('/\D/', '', parse_url($request->url(), PHP_URL_PATH)),
            'title' => 'Una película',
            'overview' => '',
            'tagline' => '',
            'release_date' => '2000-01-01',
            'translations' => ['translations' => []],
            'credits' => ['cast' => [], 'crew' => []],
        ]));
    }

    public function test_crawlers_cannot_trigger_imports()
    {
        Http::fake();

        $this->withHeader('User-Agent', self::GOOGLEBOT)->get('/resolve/movie/603')->assertNotFound();
        $this->withHeader('User-Agent', self::GOOGLEBOT)->get('/resolve/person/1234')->assertNotFound();

        Http::assertNothingSent();
        $this->assertSame(0, Title::count());
    }

    public function test_crawlers_still_get_titles_that_are_already_imported()
    {
        $title = Title::factory()->create(['tmdb_id' => 603]);

        $this->withHeader('User-Agent', self::GOOGLEBOT)
            ->get('/resolve/movie/603')
            ->assertRedirect(route('films.show', $title, absolute: false));
    }

    public function test_guests_share_a_daily_import_quota()
    {
        config(['services.tmdb.guest_imports_per_day' => 2]);
        $this->fakeMovies();

        $this->get('/resolve/movie/1')->assertRedirect();
        $this->get('/resolve/movie/2')->assertRedirect();
        $this->get('/resolve/movie/3')->assertTooManyRequests();

        // Lo ya importado sigue andando aunque el cupo esté agotado
        $this->get('/resolve/movie/1')->assertRedirect();

        // Y los usuarios logueados no consumen ni dependen del cupo
        $this->actingAs(User::factory()->create())->get('/resolve/movie/3')->assertRedirect();
        $this->assertSame(3, Title::count());
    }

    public function test_human_visits_are_recorded_and_crawler_visits_are_not()
    {
        $title = Title::factory()->create();
        $person = Person::factory()->create();

        $this->withHeader('User-Agent', self::GOOGLEBOT)->get(route('films.show', $title))->assertOk();
        $this->withHeader('User-Agent', self::GOOGLEBOT)->get(route('people.show', $person))->assertOk();

        $this->assertNull($title->fresh()->viewed_at);
        $this->assertNull($person->fresh()->viewed_at);

        $this->flushHeaders();
        $this->get(route('films.show', $title))->assertOk();
        $this->get(route('people.show', $person))->assertOk();

        $this->assertNotNull($title->fresh()->viewed_at);
        $this->assertNotNull($person->fresh()->viewed_at);
    }

    public function test_recording_a_visit_does_not_touch_updated_at()
    {
        $title = Title::factory()->create(['updated_at' => now()->subWeek()]);

        $this->get(route('films.show', $title))->assertOk();

        $this->assertTrue($title->fresh()->updated_at->lt(now()->subDays(6)));
    }
}
