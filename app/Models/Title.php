<?php

namespace App\Models;

use App\Enums\TitleType;
use App\Models\Concerns\HasRatingAggregates;
use App\Models\Concerns\HasTranslations;
use Database\Factories\TitleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Title extends Model
{
    /** @use HasFactory<TitleFactory> */
    use HasFactory, HasRatingAggregates, HasTranslations;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'type' => TitleType::class,
            'translations' => 'array',
            'genres' => 'array',
            'credits' => 'array',
            'ratings_histogram' => 'array',
            'release_date' => 'date',
            'last_air_date' => 'date',
            'popularity' => 'float',
            'synced_at' => 'datetime',
        ];
    }

    public function seasons(): HasMany
    {
        return $this->hasMany(Season::class)->orderBy('season_number');
    }

    public function episodes(): HasMany
    {
        return $this->hasMany(Episode::class);
    }

    public function likes(): MorphMany
    {
        return $this->morphMany(Like::class, 'likeable');
    }

    public function diaryEntries(): MorphMany
    {
        return $this->morphMany(DiaryEntry::class, 'loggable');
    }

    public function reviews(): MorphMany
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isMovie(): bool
    {
        return $this->type === TitleType::Movie;
    }

    public function isTv(): bool
    {
        return $this->type === TitleType::Tv;
    }

    public function getYearAttribute(): ?int
    {
        return $this->release_date?->year;
    }

    /**
     * Un título está "viejo" cuando su snapshot supera el TTL según su tipo:
     * películas 30 días; series en emisión 24 h; series terminadas 30 días.
     */
    public function isStale(): bool
    {
        if ($this->synced_at === null) {
            return true;
        }

        $threshold = match (true) {
            $this->isMovie() => now()->subDays(30),
            $this->isAiring() => now()->subDay(),
            default => now()->subDays(30),
        };

        return $this->synced_at->lt($threshold);
    }

    public function isAiring(): bool
    {
        return in_array($this->tv_status, ['Returning Series', 'In Production', 'Planned'], true);
    }

    public function scopeMovies(Builder $query): Builder
    {
        return $query->where('type', TitleType::Movie);
    }

    public function scopeTv(Builder $query): Builder
    {
        return $query->where('type', TitleType::Tv);
    }
}
