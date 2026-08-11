<?php

namespace App\Jobs;

use App\Models\Person;
use App\Services\Tmdb\TmdbImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

class RefreshPerson implements ShouldQueue
{
    use Queueable;

    public function __construct(public Person $person) {}

    public function middleware(): array
    {
        return [new RateLimited('tmdb')];
    }

    public function handle(TmdbImportService $importer): void
    {
        $importer->importPerson($this->person->tmdb_id);
    }
}
