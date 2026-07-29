<?php

namespace Tests\Feature\Import;

use App\Jobs\PrepareLetterboxdImport;
use App\Models\LetterboxdImport;
use App\Models\ListItem;
use App\Models\ListModel;
use App\Models\Rating;
use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class LetterboxdImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.key' => 'test-key']);
        Storage::fake('local');

        $this->user = User::factory()->create();
    }

    public function test_upload_validates_and_dispatches_the_prepare_job()
    {
        Queue::fake();

        $this->actingAs($this->user)
            ->post('/settings/import', [
                'file' => UploadedFile::fake()->create('datos.txt', 10),
                'sections' => ['watched' => true],
            ])
            ->assertSessionHasErrors('file');

        $this->actingAs($this->user)
            ->post('/settings/import', [
                'file' => $this->makeExportZip(),
                'sections' => ['watched' => false, 'diary' => false, 'watchlist' => false, 'likes' => false, 'lists' => false],
            ])
            ->assertSessionHasErrors('sections');

        $this->actingAs($this->user)
            ->post('/settings/import', [
                'file' => $this->makeExportZip(),
                'sections' => ['watched' => true, 'diary' => true],
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('letterboxd_imports', ['user_id' => $this->user->id, 'status' => 'pending']);
        Queue::assertPushed(PrepareLetterboxdImport::class);

        // No se permite un segundo import mientras hay uno activo
        $this->actingAs($this->user)
            ->post('/settings/import', [
                'file' => $this->makeExportZip(),
                'sections' => ['watched' => true],
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_full_pipeline_imports_matches_and_reports_unmatched()
    {
        $this->fakeTmdb();
        config(['queue.default' => 'sync']); // toda la cadena corre inline

        $this->actingAs($this->user)->post('/settings/import', [
            'file' => $this->makeExportZip(),
            'sections' => ['watched' => true, 'diary' => true, 'watchlist' => true, 'likes' => true, 'lists' => true],
        ])->assertSessionHasNoErrors();

        $import = LetterboxdImport::first();

        $this->assertSame('completed', $import->status);
        // 3 películas únicas: The Matrix, Burning y la sin match
        $this->assertSame(3, $import->total);
        $this->assertSame(3, $import->processed);
        $this->assertSame(2, $import->matched);
        $this->assertCount(1, $import->unmatched);
        $this->assertSame('Unknown Film XYZ', $import->unmatched[0]['name']);

        $matrix = Title::where('tmdb_id', 603)->first();
        $this->assertNotNull($matrix);

        // Watched + rating (4.5★ → 9) + like
        $this->assertDatabaseHas('watched_titles', ['user_id' => $this->user->id, 'title_id' => $matrix->id]);
        $this->assertDatabaseHas('ratings', ['user_id' => $this->user->id, 'rateable_id' => $matrix->id, 'value' => 9]);
        $this->assertDatabaseHas('likes', ['user_id' => $this->user->id, 'likeable_type' => 'title', 'likeable_id' => $matrix->id]);

        // Diario deduplicado (reviews.csv + diary.csv = 1 entrada) con reseña multilínea intacta
        $this->assertSame(1, $this->user->diaryEntries()->count());
        $entry = $this->user->diaryEntries()->first();
        $this->assertSame('2020-05-06', $entry->watched_on->toDateString());
        $this->assertSame(['cine'], $entry->tags);
        $this->assertSame("Línea uno.\nLínea dos.", $entry->review->body);

        // Watchlist: Burning sí; The Matrix no (ya está vista)
        $burning = Title::where('tmdb_id', 491584)->first();
        $this->assertDatabaseHas('watchlist_items', ['user_id' => $this->user->id, 'title_id' => $burning->id]);
        $this->assertDatabaseMissing('watchlist_items', ['user_id' => $this->user->id, 'title_id' => $matrix->id]);

        // Lista con descripción y posiciones
        $this->assertDatabaseHas('lists', ['user_id' => $this->user->id, 'name' => 'Mis Favoritas', 'description' => 'Las mejores']);
        $list = ListModel::first();
        $this->assertSame([1, 2], $list->items()->orderBy('position')->pluck('position')->all());

        // Resumen
        $this->assertSame(1, $import->summary['ratings']);
        $this->assertSame(1, $import->summary['diary']);
        $this->assertSame(1, $import->summary['reviews']);
        $this->assertSame(1, $import->summary['lists']);
        $this->assertSame(2, $import->summary['listItems']);

        // El ZIP se limpia al terminar
        Storage::assertMissing($import->zip_path);
    }

    public function test_existing_movieboxd_data_is_preserved()
    {
        $this->fakeTmdb();
        config(['queue.default' => 'sync']);

        // El usuario ya calificó The Matrix con 1★ (valor 2) en Movieboxd
        $matrix = Title::factory()->create(['tmdb_id' => 603, 'title' => 'The Matrix']);
        Rating::create(['user_id' => $this->user->id, 'rateable_type' => 'title', 'rateable_id' => $matrix->id, 'value' => 2]);

        $this->actingAs($this->user)->post('/settings/import', [
            'file' => $this->makeExportZip(),
            'sections' => ['watched' => true],
        ]);

        // El rating del export (9) NO pisa el propio (2)
        $this->assertDatabaseHas('ratings', ['user_id' => $this->user->id, 'rateable_id' => $matrix->id, 'value' => 2]);
        $this->assertSame(1, Rating::where('rateable_id', $matrix->id)->count());
    }

    public function test_rerunning_the_import_is_idempotent()
    {
        $this->fakeTmdb();
        config(['queue.default' => 'sync']);

        $sections = ['watched' => true, 'diary' => true, 'watchlist' => true, 'likes' => true, 'lists' => true];

        $this->actingAs($this->user)->post('/settings/import', ['file' => $this->makeExportZip(), 'sections' => $sections]);
        $this->actingAs($this->user)->post('/settings/import', ['file' => $this->makeExportZip(), 'sections' => $sections]);

        $this->assertSame(2, LetterboxdImport::where('status', 'completed')->count());

        // Nada duplicado
        $this->assertSame(1, $this->user->diaryEntries()->count());
        $this->assertSame(1, $this->user->reviews()->count());
        $this->assertSame(1, ListModel::count());
        $this->assertSame(2, ListItem::count());
        // Solo The Matrix queda vista (la otra watched del export no tiene match)
        $this->assertSame(1, $this->user->watchedTitles()->count());
    }

    public function test_import_page_renders_own_history()
    {
        LetterboxdImport::create([
            'user_id' => $this->user->id,
            'status' => 'completed',
            'zip_path' => 'x.zip',
            'options' => ['watched' => true],
            'summary' => ['watched' => 5],
        ]);

        $this->actingAs($this->user)->get('/settings/import')->assertInertia(
            fn ($page) => $page->component('settings/Import')->has('imports', 1)->where('hasActiveImport', false)
        );
    }

    /**
     * ZIP de exportación mínimo pero realista (con carpeta raíz, reseña
     * multilínea, entrada duplicada entre diary y reviews, y una película
     * inexistente en TMDB).
     */
    protected function makeExportZip(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'lbxd').'.zip';

        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $root = 'letterboxd-test-export/';

        $zip->addFromString($root.'watched.csv', implode("\n", [
            'Date,Name,Year,Letterboxd URI',
            '2020-04-05,The Matrix,1999,https://boxd.it/x',
            '2020-04-06,Unknown Film XYZ,1980,https://boxd.it/y',
        ]));

        $zip->addFromString($root.'ratings.csv', implode("\n", [
            'Date,Name,Year,Letterboxd URI,Rating',
            '2020-04-05,The Matrix,1999,https://boxd.it/x,4.5',
        ]));

        $zip->addFromString($root.'diary.csv', implode("\n", [
            'Date,Name,Year,Letterboxd URI,Rating,Rewatch,Tags,Watched Date',
            '2020-05-07,The Matrix,1999,https://boxd.it/x,4.5,,cine,2020-05-06',
        ]));

        $zip->addFromString($root.'reviews.csv', implode("\n", [
            'Date,Name,Year,Letterboxd URI,Rating,Rewatch,Review,Tags,Watched Date',
            '2020-05-07,The Matrix,1999,https://boxd.it/x,4.5,,"Línea uno.',
            'Línea dos.",cine,2020-05-06',
        ]));

        $zip->addFromString($root.'watchlist.csv', implode("\n", [
            'Date,Name,Year,Letterboxd URI',
            '2020-04-05,Burning,2018,https://boxd.it/b',
            '2020-04-05,The Matrix,1999,https://boxd.it/x',
        ]));

        $zip->addFromString($root.'likes/films.csv', implode("\n", [
            'Date,Name,Year,Letterboxd URI',
            '2020-04-05,The Matrix,1999,https://boxd.it/x',
        ]));

        $zip->addFromString($root.'lists/mis-favoritas.csv', implode("\n", [
            'Letterboxd list export v7',
            'Date,Name,Tags,URL,Description',
            '2020-04-06,Mis Favoritas,,https://boxd.it/l,Las mejores',
            '',
            'Position,Name,Year,URL,Description',
            '1,The Matrix,1999,https://boxd.it/x,',
            '2,Burning,2018,https://boxd.it/b,',
        ]));

        // Carpetas que se deben ignorar
        $zip->addFromString($root.'deleted/diary.csv', "Date,Name,Year,Letterboxd URI\n2020-01-01,Borrada,2000,https://boxd.it/z");

        $zip->close();

        return new UploadedFile($path, 'letterboxd-export.zip', 'application/zip', null, true);
    }

    protected function fakeTmdb(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            $path = parse_url($url, PHP_URL_PATH);
            parse_str(parse_url($url, PHP_URL_QUERY) ?? '', $query);

            if (str_contains($path, '/search/movie')) {
                return Http::response(match ($query['query'] ?? '') {
                    'The Matrix' => ['results' => [['id' => 603, 'title' => 'The Matrix', 'release_date' => '1999-03-31']]],
                    'Burning' => ['results' => [['id' => 491584, 'title' => 'Burning', 'release_date' => '2018-05-17']]],
                    default => ['results' => []],
                });
            }

            if (str_contains($path, '/search/multi')) {
                return Http::response(['results' => []]);
            }

            if (str_contains($path, '/movie/603')) {
                return Http::response($this->moviePayload(603, 'The Matrix', '1999-03-31'));
            }

            if (str_contains($path, '/movie/491584')) {
                return Http::response($this->moviePayload(491584, 'Burning', '2018-05-17'));
            }

            return Http::response(['results' => []], 404);
        });
    }

    protected function moviePayload(int $id, string $title, string $releaseDate): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'original_title' => $title,
            'overview' => 'Overview',
            'tagline' => '',
            'poster_path' => '/p.jpg',
            'backdrop_path' => '/b.jpg',
            'release_date' => $releaseDate,
            'runtime' => 120,
            'genres' => [],
            'original_language' => 'en',
            'popularity' => 10,
            'credits' => ['cast' => [], 'crew' => []],
            'translations' => ['translations' => []],
        ];
    }
}
