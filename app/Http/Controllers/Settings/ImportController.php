<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Jobs\PrepareLetterboxdImport;
use App\Models\LetterboxdImport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ImportController extends Controller
{
    public function show(Request $request): Response
    {
        $imports = LetterboxdImport::where('user_id', $request->user()->id)
            ->latest()
            ->limit(5)
            ->get();

        return Inertia::render('settings/Import', [
            'imports' => $imports->map(fn (LetterboxdImport $import) => [
                'id' => $import->id,
                'status' => $import->status,
                'total' => $import->total,
                'processed' => $import->processed,
                'matched' => $import->matched,
                'summary' => $import->summary,
                'unmatched' => $import->unmatched ?? [],
                'error' => $import->error,
                'createdAt' => $import->created_at->toDateTimeString(),
                'finishedAt' => $import->finished_at?->toDateTimeString(),
            ]),
            'hasActiveImport' => $imports->contains(fn (LetterboxdImport $import) => $import->isActive()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:51200'],
            'sections' => ['required', 'array'],
            'sections.watched' => ['boolean'],
            'sections.diary' => ['boolean'],
            'sections.watchlist' => ['boolean'],
            'sections.likes' => ['boolean'],
            'sections.lists' => ['boolean'],
        ]);

        $options = collect($request->input('sections'))
            ->only(['watched', 'diary', 'watchlist', 'likes', 'lists'])
            ->map(fn ($value) => filter_var($value, FILTER_VALIDATE_BOOLEAN));

        if (! $options->contains(true)) {
            return back()->withErrors(['sections' => __('app.import_no_sections')]);
        }

        $userId = $request->user()->id;

        if (LetterboxdImport::where('user_id', $userId)->whereIn('status', ['pending', 'processing'])->exists()) {
            return back()->withErrors(['file' => __('app.import_already_running')]);
        }

        $path = $request->file('file')->store("letterboxd/{$userId}");

        $import = LetterboxdImport::create([
            'user_id' => $userId,
            'status' => 'pending',
            'zip_path' => $path,
            'options' => $options->all(),
        ]);

        PrepareLetterboxdImport::dispatch($import);

        return back();
    }
}
