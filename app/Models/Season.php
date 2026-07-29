<?php

namespace App\Models;

use App\Models\Concerns\HasRatingAggregates;
use App\Models\Concerns\HasTranslations;
use Database\Factories\SeasonFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Season extends Model
{
    /** @use HasFactory<SeasonFactory> */
    use HasFactory, HasRatingAggregates, HasTranslations;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'ratings_histogram' => 'array',
            'air_date' => 'date',
            'synced_at' => 'datetime',
        ];
    }

    public function title(): BelongsTo
    {
        return $this->belongsTo(Title::class);
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class)->orderBy('episode_number');
    }

    public function diaryEntries(): MorphMany
    {
        return $this->morphMany(DiaryEntry::class, 'loggable');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    /**
     * Los episodios se importan lazy: al crear el stub synced_at queda null.
     * Para shows en emisión se refrescan cada 7 días.
     */
    public function needsEpisodeSync(): bool
    {
        if ($this->synced_at === null) {
            return true;
        }

        return $this->title->isAiring() && $this->synced_at->lt(now()->subDays(7));
    }

    protected function titleField(): string
    {
        return 'name';
    }
}
