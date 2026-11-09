<?php

namespace Tests\Feature\Api;

use App\Models\Title;
use App\Models\User;
use App\Models\WatchedTitle;
use App\Models\WatchlistItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class WatchlistApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.tmdb.key' => 'test-key']);
        $this->user = User::factory()->create(['username' => 'pablo']);
        $this->token = $this->user->createToken('Hoy Trasnoche', ['watchlist'])->plainTextToken;
    }

    private function api(): static
    {
        return $this->withToken($this->token);
    }

    /**
     * TMDB falso: devuelve resultados según la consulta y el idioma pedidos.
     *
     * @param  array<string, list<array{id: int, es: string, en: string, original: string, year: int, popularity?: float}>>  $porConsulta
     */
    private function fakeSearch(array $porConsulta): void
    {
        Http::fake(function (HttpRequest $request) use ($porConsulta) {
            if (! str_contains($request->url(), '/search/movie')) {
                return Http::response([], 404);
            }
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $q);
            $year = isset($q['primary_release_year']) ? (int) $q['primary_release_year'] : null;
            $results = collect($porConsulta[$q['query']] ?? [])
                ->filter(fn ($m) => $year === null || $m['year'] === $year)
                ->map(fn ($m) => [
                    'id' => $m['id'],
                    'title' => $q['language'] === 'es-MX' ? $m['es'] : $m['en'],
                    'original_title' => $m['original'],
                    'release_date' => "{$m['year']}-05-30",
                    'poster_path' => "/p{$m['id']}.jpg",
                    'overview' => 'Sinopsis.',
                    'popularity' => $m['popularity'] ?? 10,
                ])->values()->all();

            return Http::response(['results' => $results]);
        });
    }

    public function test_requires_a_valid_token_with_the_watchlist_ability(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->get('/api/v1/me')->assertUnauthorized()->assertJsonStructure(['message']);
        $this->withToken('1|inventado')->getJson('/api/v1/me')->assertUnauthorized();

        $sinHabilidad = $this->user->createToken('otro', ['otra-cosa'])->plainTextToken;
        $this->withToken($sinHabilidad)->getJson('/api/v1/me')->assertForbidden();

        // En los tests el guard recuerda al usuario entre pedidos: se olvida para cambiar de token.
        $this->app['auth']->forgetGuards();
        $this->api()->getJson('/api/v1/me')->assertOk()->assertJson(['username' => 'pablo']);
    }

    public function test_match_returns_the_single_exact_film_by_spanish_title_and_year(): void
    {
        $this->fakeSearch([
            'Parásitos' => [
                ['id' => 496243, 'es' => 'Parásitos', 'en' => 'Parasite', 'original' => '기생충', 'year' => 2019, 'popularity' => 50],
                ['id' => 999, 'es' => 'Parásitos del espacio', 'en' => 'Space Parasites', 'original' => 'Space Parasites', 'year' => 2019],
            ],
        ]);

        $this->api()->getJson('/api/v1/match?title=Parásitos&year=2019')
            ->assertOk()
            ->assertJsonPath('status', 'matched')
            ->assertJsonPath('title.tmdb_id', 496243)
            ->assertJsonPath('title.title', 'Parásitos')
            ->assertJsonPath('title.year', 2019)
            ->assertJsonPath('title.in_watchlist', false)
            ->assertJsonPath('title.url', null);

        $this->assertSame(0, Title::count(), 'match no importa nada');
    }

    public function test_match_by_the_alternate_title(): void
    {
        $this->fakeSearch([
            'Relatos salvajes' => [],
            'Wild Tales' => [['id' => 265195, 'es' => 'Relatos salvajes', 'en' => 'Wild Tales', 'original' => 'Relatos salvajes', 'year' => 2014]],
        ]);

        $this->api()->getJson('/api/v1/match?title='.urlencode('¡Relatos salvajes!').'&alt=Wild%20Tales&year=2014')
            ->assertOk()
            ->assertJsonPath('status', 'matched')
            ->assertJsonPath('title.tmdb_id', 265195);
    }

    public function test_match_returns_candidates_when_there_are_remakes_and_no_year(): void
    {
        $this->fakeSearch([
            'Dune' => [
                ['id' => 438631, 'es' => 'Dune', 'en' => 'Dune', 'original' => 'Dune', 'year' => 2021, 'popularity' => 90],
                ['id' => 841, 'es' => 'Dune', 'en' => 'Dune', 'original' => 'Dune', 'year' => 1984, 'popularity' => 30],
            ],
        ]);

        $this->api()->getJson('/api/v1/match?title=Dune')
            ->assertOk()
            ->assertJsonPath('status', 'ambiguous')
            ->assertJsonCount(2, 'candidates')
            ->assertJsonPath('candidates.0.tmdb_id', 438631)
            ->assertJsonPath('candidates.1.year', 1984);
    }

    public function test_match_prefers_the_year_and_reports_not_found(): void
    {
        $this->fakeSearch([
            'Dune' => [
                ['id' => 438631, 'es' => 'Dune', 'en' => 'Dune', 'original' => 'Dune', 'year' => 2021],
                ['id' => 841, 'es' => 'Dune', 'en' => 'Dune', 'original' => 'Dune', 'year' => 1984],
            ],
        ]);

        $this->api()->getJson('/api/v1/match?title=Dune&year=1984')->assertJsonPath('status', 'matched')->assertJsonPath('title.tmdb_id', 841);
        $this->api()->getJson('/api/v1/match?title=Inexistente')->assertJsonPath('status', 'not_found')->assertJsonPath('candidates', []);
        $this->api()->getJson('/api/v1/match')->assertUnprocessable();
    }

    public function test_match_tells_what_the_user_already_has(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 496243]);
        WatchedTitle::create(['user_id' => $this->user->id, 'title_id' => $title->id]);
        $this->fakeSearch(['Parásitos' => [['id' => 496243, 'es' => 'Parásitos', 'en' => 'Parasite', 'original' => '기생충', 'year' => 2019]]]);

        $this->api()->getJson('/api/v1/match?title=Parásitos')
            ->assertJsonPath('title.watched', true)
            ->assertJsonPath('title.url', route('films.show', $title));
    }

    public function test_adds_to_the_watchlist_importing_the_film_when_missing_and_is_idempotent(): void
    {
        Http::fake(['*/movie/603*' => Http::response([
            'id' => 603, 'title' => 'The Matrix', 'original_title' => 'The Matrix', 'release_date' => '1999-03-31',
            'overview' => 'A hacker.', 'tagline' => '', 'poster_path' => '/m.jpg', 'backdrop_path' => null,
            'runtime' => 136, 'genres' => [], 'original_language' => 'en',
            'popularity' => 80, 'credits' => ['cast' => [], 'crew' => []],
            'translations' => ['translations' => [['iso_639_1' => 'es', 'data' => ['title' => 'Matrix']]]],
        ])]);

        $this->api()->postJson('/api/v1/watchlist', ['tmdb_id' => 603])
            ->assertCreated()
            ->assertJsonPath('status', 'added')
            ->assertJsonPath('title.title', 'Matrix')
            ->assertJsonPath('title.in_watchlist', true);

        $title = Title::where('tmdb_id', 603)->firstOrFail();
        $this->assertSame(1, $title->watchlist_count);

        $this->api()->postJson('/api/v1/watchlist', ['tmdb_id' => 603])->assertOk()->assertJsonPath('status', 'already_in_watchlist');
        $this->assertSame(1, WatchlistItem::count());
    }

    public function test_does_not_add_a_film_already_watched(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 603]);
        WatchedTitle::create(['user_id' => $this->user->id, 'title_id' => $title->id]);

        $this->api()->postJson('/api/v1/watchlist', ['tmdb_id' => 603])->assertStatus(409)->assertJsonPath('status', 'already_watched');
        $this->assertSame(0, WatchlistItem::count());
    }

    public function test_unknown_tmdb_id_is_a_404_and_tmdb_down_is_a_503(): void
    {
        Http::fake(['*/movie/1*' => Http::response(['status_message' => 'not found'], 404)]);
        $this->api()->postJson('/api/v1/watchlist', ['tmdb_id' => 1])->assertNotFound()->assertJsonPath('status', 'not_found');

        Http::fake(['*' => Http::response([], 500)]);
        $this->api()->getJson('/api/v1/match?title=Algo')->assertStatus(503)->assertJsonStructure(['error']);
    }

    public function test_removes_from_the_watchlist(): void
    {
        $title = Title::factory()->create(['tmdb_id' => 603]);
        WatchlistItem::create(['user_id' => $this->user->id, 'title_id' => $title->id]);

        $this->api()->deleteJson('/api/v1/watchlist/603')->assertOk()->assertJsonPath('status', 'removed')->assertJsonPath('title.in_watchlist', false);
        $this->assertSame(0, $title->fresh()->watchlist_count);
        $this->api()->deleteJson('/api/v1/watchlist/603')->assertNotFound();
    }

    public function test_status_reports_many_films_at_once_for_the_token_user(): void
    {
        $enLista = Title::factory()->create(['tmdb_id' => 603]);
        $vista = Title::factory()->create(['tmdb_id' => 496243]);
        $ajena = Title::factory()->create(['tmdb_id' => 129]);
        WatchlistItem::create(['user_id' => $this->user->id, 'title_id' => $enLista->id]);
        WatchedTitle::create(['user_id' => $this->user->id, 'title_id' => $vista->id]);
        WatchlistItem::create(['user_id' => User::factory()->create()->id, 'title_id' => $ajena->id]);

        $this->api()->getJson('/api/v1/watchlist/status?tmdb_ids=603,496243,129,777')
            ->assertOk()
            ->assertJsonPath('titles.603.in_watchlist', true)
            ->assertJsonPath('titles.603.url', route('films.show', $enLista))
            ->assertJsonPath('titles.496243.watched', true)
            ->assertJsonPath('titles.129.in_watchlist', false)
            ->assertJsonMissingPath('titles.777');

        $this->api()->getJson('/api/v1/watchlist/status')->assertUnprocessable();
    }

    public function test_a_token_only_touches_its_own_user(): void
    {
        $otro = User::factory()->create();
        $title = Title::factory()->create(['tmdb_id' => 603]);
        WatchlistItem::create(['user_id' => $otro->id, 'title_id' => $title->id]);

        $this->api()->deleteJson('/api/v1/watchlist/603')->assertNotFound();
        $this->assertSame(1, WatchlistItem::where('user_id', $otro->id)->count());
    }
}
