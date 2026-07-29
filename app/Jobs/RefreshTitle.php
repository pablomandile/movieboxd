<?php

namespace App\Jobs;

use App\Models\Title;
use App\Services\Tmdb\TmdbImportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;

class RefreshTitle implements ShouldQueue
{
    use Queueable;

    public function __construct(public Title $title) {}

    public function middleware(): array
    {
        return [new RateLimited('tmdb')];
    }

    public function handle(TmdbImportService $importer): void
    {
        $importer->refresh($this->title);
    }
}
