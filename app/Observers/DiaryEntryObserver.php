<?php

namespace App\Observers;

use App\Models\DiaryEntry;
use App\Models\Episode;
use App\Models\EpisodeWatch;
use App\Models\Season;
use App\Models\Title;
use App\Models\WatchedTitle;

class DiaryEntryObserver
{
    /**
     * Loguear implica marcar como visto (regla 1: el diario y el flag
     * watched son independientes, pero el log hace upsert del flag).
     */
    public function created(DiaryEntry $entry): void
    {
        $loggable = $entry->loggable;

        if ($loggable instanceof Title) {
            WatchedTitle::firstOrCreate([
                'user_id' => $entry->user_id,
                'title_id' => $loggable->id,
            ]);
        }

        if ($loggable instanceof Episode) {
            EpisodeWatch::firstOrCreate(
                ['user_id' => $entry->user_id, 'episode_id' => $loggable->id],
                [
                    'title_id' => $loggable->title_id,
                    'season_id' => $loggable->season_id,
                    'watched_at' => now(),
                ]
            );
        }

        if ($loggable instanceof Season) {
            foreach ($loggable->episodes as $episode) {
                EpisodeWatch::firstOrCreate(
                    ['user_id' => $entry->user_id, 'episode_id' => $episode->id],
                    [
                        'title_id' => $episode->title_id,
                        'season_id' => $episode->season_id,
                        'watched_at' => now(),
                    ]
                );
            }
        }
    }
}
