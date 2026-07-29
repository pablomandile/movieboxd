<?php

namespace App\Models;

use App\Models\Concerns\HasRatingAggregates;
use App\Models\Concerns\HasTranslations;
use Database\Factories\EpisodeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Episode extends Model
{
    /** @use HasFactory<EpisodeFactory> */
    use HasFactory, HasRatingAggregates, HasTranslations;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'ratings_histogram' => 'array',
            'air_date' => 'date',
        ];
    }

    public function season(): BelongsTo
    {
        return $this->belongsTo(Season::class);
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function watches(): HasMany
    {
        return $this->hasMany(EpisodeWatch::class);
    }

    public function diaryEntries(): MorphMany
    {
        return $this->morphMany(DiaryEntry::class, 'loggable');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function getCodeAttribute(): string
    {
        return sprintf('S%02dE%02d', $this->season_number, $this->episode_number);
    }

    protected function titleField(): string
    {
        return 'name';
    }
}
