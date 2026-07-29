<?php

namespace App\Jobs;

use App\Models\LetterboxdImport;
use App\Models\LetterboxdImportItem;
use App\Services\Letterboxd\LetterboxdExportParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Abre el ZIP del export, lo parsea y crea los ítems a procesar.
 */
class PrepareLetterboxdImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public LetterboxdImport $import) {}

    public function handle(LetterboxdExportParser $parser): void
    {
        $import = $this->import;

        try {
            $result = $parser->parse(Storage::path($import->zip_path), $import->options);

            $now = now();

            collect($result['films'])
                ->values()
                ->map(fn (array $film) => [
                    'import_id' => $import->id,
                    'name' => $film['name'],
                    'year' => $film['year'],
                    'payload' => json_encode($film),
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->chunk(200)
                ->each(fn ($chunk) => LetterboxdImportItem::insert($chunk->all()));

            $import->update([
                'status' => 'processing',
                'total' => count($result['films']),
                'lists_meta' => $result['lists'],
            ]);

            ProcessLetterboxdImport::dispatch($import);
        } catch (Throwable $exception) {
            $import->update([
                'status' => 'failed',
                'error' => Str::limit($exception->getMessage(), 500),
                'finished_at' => now(),
            ]);

            report($exception);
        }
    }
}
