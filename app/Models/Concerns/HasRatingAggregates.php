<?php

namespace App\Models\Concerns;

use App\Models\Rating;
use App\Models\Setting;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;

/**
 * Agregados de rating cacheados en columnas (ratings_count, ratings_sum,
 * ratings_histogram). Se recalculan desde la tabla ratings en cada cambio:
 * una query GROUP BY por escritura — sin drift posible, sin matemática de deltas.
 */
trait HasRatingAggregates
{
    public function ratings(): MorphMany
    {
        return $this->morphMany(Rating::class, 'rateable');
    }

    public function recalculateRatingAggregates(): void
    {
        $buckets = $this->ratings()
            ->selectRaw('value, COUNT(*) as total')
            ->groupBy('value')
            ->pluck('total', 'value');

        $histogram = [];
        $sum = 0;

        for ($value = 1; $value <= 10; $value++) {
            $count = (int) ($buckets[$value] ?? 0);
            $histogram[(string) $value] = $count;
            $sum += $value * $count;
        }

        $this->forceFill([
            'ratings_count' => array_sum($histogram),
            'ratings_sum' => $sum,
            'ratings_histogram' => $histogram,
        ])->saveQuietly();
    }

    /**
     * Promedio ponderado estilo Letterboxd/IMDb: los títulos con pocos votos
     * se amortiguan hacia la media global.
     *
     * WR = (v/(v+m))·R + (m/(v+m))·C
     */
    public function getWeightedRatingAttribute(): ?float
    {
        $v = (int) $this->ratings_count;

        if ($v === 0) {
            return null;
        }

        $m = (int) (Setting::get('rating.prior') ?? config('movieboxd.rating_prior', 30));
        $r = $this->ratings_sum / $v / 2;
        $c = static::globalRatingAverage();

        return round(($v / ($v + $m)) * $r + ($m / ($v + $m)) * $c, 2);
    }

    public static function globalRatingAverage(): float
    {
        return (float) Cache::remember(
            'ratings:global-average',
            now()->addHour(),
            fn () => ((float) Rating::avg('value') ?: 6.0) / 2
        );
    }
}
