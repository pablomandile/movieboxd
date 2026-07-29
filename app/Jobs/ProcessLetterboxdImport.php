<?php

namespace App\Jobs;

use App\Models\LetterboxdImport;
use App\Services\Letterboxd\LetterboxdImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

/**
 * Procesa el import por tandas de 20 ítems y se re-despacha a sí mismo
 * hasta agotar los pendientes: secuencial (auto-limitado contra TMDB),
 * resumible y con progreso visible en cada tanda.
 */
class ProcessLetterboxdImport implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public const CHUNK = 20;

    public function __construct(public LetterboxdImport $import) {}

    public function handle(LetterboxdImportService $service): void
    {
        $import = $this->import->fresh();

        if ($import === null || $import->status !== 'processing') {
            return;
        }

        $items = $import->items()->where('status', 'pending')->limit(self::CHUNK)->get();

        $summary = $import->summary ?? [];
        $unmatched = $import->unmatched ?? [];

        foreach ($items as $item) {
            $counts = $service->processItem($item);

            foreach ($counts as $key => $value) {
                $summary[$key] = ($summary[$key] ?? 0) + $value;
            }

            if (in_array($item->status, ['unmatched', 'failed'], true)) {
                $unmatched[] = ['name' => $item->name, 'year' => $item->year, 'reason' => $item->error];
            }
        }

        $import->update([
            'processed' => $import->items()->where('status', '!=', 'pending')->count(),
            'matched' => $import->items()->where('status', 'matched')->count(),
            'summary' => $summary,
            'unmatched' => $unmatched,
        ]);

        if ($import->items()->where('status', 'pending')->exists()) {
            static::dispatch($import);

            return;
        }

        $this->finalize($import, $service);
    }

    protected function finalize(LetterboxdImport $import, LetterboxdImportService $service): void
    {
        $summary = $import->summary ?? [];

        if ($import->wantsSection('lists')) {
            $created = $service->createLists($import);
            $summary['lists'] = $created['lists'];
            $summary['listItems'] = $created['listItems'];
        }

        // Tras un bulk grande, deja todos los contadores cacheados exactos
        Artisan::call('movieboxd:reconcile-aggregates');

        $import->update([
            'status' => 'completed',
            'summary' => $summary,
            'finished_at' => now(),
        ]);

        Storage::delete($import->zip_path);
    }
}
