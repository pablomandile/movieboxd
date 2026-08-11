<?php

namespace Tests\Feature\Catalog;

use App\Jobs\RefreshPerson;
use App\Models\Person;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PersonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);
    }

    /** Payload con la forma real de /person/{id}?append_to_response=combined_credits */
    protected function fakePerson(array $overrides = []): void
    {
        Http::fake([
            '*/person/1234*' => Http::response(array_merge([
                'id' => 1234,
                'name' => 'Spike Jonze',
                'biography' => 'Director estadounidense.',
                'profile_path' => '/perfil.jpg',
                'birthday' => '1969-10-22',
                'deathday' => null,
                'place_of_birth' => 'Rockville, Maryland',
                'known_for_department' => 'Directing',
                'popularity' => 8.5,
                'translations' => ['translations' => []],
                'combined_credits' => [
                    'cast' => [
                        [
                            'id' => 500, 'media_type' => 'movie', 'title' => 'Cameo', 'character' => 'Él mismo',
                            'release_date' => '2010-01-01', 'poster_path' => '/c.jpg', 'popularity' => 1,
                        ],
                    ],
                    'crew' => [
                        [
                            'id' => 152601, 'media_type' => 'movie', 'title' => 'Her', 'job' => 'Director',
                            'department' => 'Directing', 'release_date' => '2013-12-18', 'poster_path' => '/h.jpg', 'popularity' => 20,
                        ],
                        [
                            'id' => 1, 'media_type' => 'movie', 'title' => 'Being John Malkovich', 'job' => 'Director',
                            'department' => 'Directing', 'release_date' => '1999-10-29', 'poster_path' => '/b.jpg', 'popularity' => 15,
                        ],
                        // Mismo título repetido: TMDB lo lista una vez por cargo
                        [
                            'id' => 152601, 'media_type' => 'movie', 'title' => 'Her', 'job' => 'Director',
                            'department' => 'Directing', 'release_date' => '2013-12-18', 'poster_path' => '/h.jpg', 'popularity' => 20,
                        ],
                        [
                            'id' => 152601, 'media_type' => 'movie', 'title' => 'Her', 'job' => 'Producer',
                            'department' => 'Production', 'release_date' => '2013-12-18', 'poster_path' => '/h.jpg', 'popularity' => 20,
                        ],
                    ],
                ],
            ], $overrides)),
        ]);
    }

    public function test_visiting_a_person_imports_it_and_redirects_to_its_url()
    {
        $this->fakePerson();

        $this->get('/resolve/person/1234')->assertRedirect('/person/spike-jonze');

        $this->assertDatabaseHas('people', ['tmdb_id' => 1234, 'name' => 'Spike Jonze', 'slug' => 'spike-jonze']);
    }

    public function test_the_filmography_is_grouped_by_role_and_deduplicated()
    {
        $this->fakePerson();

        $this->get('/resolve/person/1234');
        $credits = Person::where('tmdb_id', 1234)->first()->credits;

        // Dirección: Her aparece dos veces en el payload, una sola acá
        $this->assertCount(2, $credits['directing']);
        $this->assertSame('Her', $credits['directing'][0]['title']);       // más reciente primero
        $this->assertSame('Being John Malkovich', $credits['directing'][1]['title']);

        $this->assertCount(1, $credits['acting']);
        $this->assertSame('Él mismo', $credits['acting'][0]['role']);

        // Producer va a producción, no a dirección
        $this->assertCount(1, $credits['producing']);
        $this->assertSame('Producer', $credits['producing'][0]['role']);
    }

    public function test_the_page_renders_with_its_filmography()
    {
        $this->fakePerson();
        $this->get('/resolve/person/1234');

        $this->get('/person/spike-jonze')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Person')
                ->where('person.name', 'Spike Jonze')
                ->where('person.knownFor', 'Directing')
                ->has('person.credits.directing', 2)
                ->has('meta')
            );
    }

    public function test_an_already_imported_person_does_not_hit_the_api_again()
    {
        $this->fakePerson();
        $this->get('/resolve/person/1234');

        Http::fake(fn () => throw new \RuntimeException('no debería llamar a TMDB'));

        $this->get('/person/spike-jonze')->assertOk();
    }

    public function test_a_stale_person_is_refreshed_in_the_background()
    {
        $this->fakePerson();
        $this->get('/resolve/person/1234');

        Person::where('tmdb_id', 1234)->update(['synced_at' => now()->subDays(45)]);
        Queue::fake();

        // La página se sirve del snapshot; el refresco va a la cola
        $this->get('/person/spike-jonze')->assertOk();

        Queue::assertPushed(RefreshPerson::class);
    }

    public function test_slug_collisions_get_the_tmdb_id_appended()
    {
        Person::factory()->create(['slug' => 'spike-jonze', 'tmdb_id' => 999, 'name' => 'Otro Spike Jonze']);
        $this->fakePerson();

        $this->get('/resolve/person/1234')->assertRedirect('/person/spike-jonze-1234');
    }

    public function test_the_spanish_biography_is_used_when_available()
    {
        $this->fakePerson([
            'translations' => [
                'translations' => [
                    ['iso_639_1' => 'es', 'data' => ['biography' => 'Biografía en español.']],
                    ['iso_639_1' => 'fr', 'data' => ['biography' => 'Biographie.']],
                ],
            ],
        ]);

        $this->get('/resolve/person/1234');

        $this->get('/person/spike-jonze')
            ->assertInertia(fn ($page) => $page->where('person.biography', 'Biografía en español.'));
    }
}
