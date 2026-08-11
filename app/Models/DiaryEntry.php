<?php

namespace App\Models;

use App\Observers\DiaryEntryObserver;
use Database\Factories\DiaryEntryFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[ObservedBy(DiaryEntryObserver::class)]
class DiaryEntry extends Model
{
    /** @use HasFactory<DiaryEntryFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'watched_on' => 'date',
            'is_rewatch' => 'boolean',
            'tags' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /**
     * Lo que se lista en el Diario. Como en Letterboxd, el Diario es la
     * bitácora: solo los visionados con reseña o marcados como revisionado.
     * Las entradas que se crean solas al calificar (ver
     * RatingController::upsert) no entran acá: esas se ven en "Lo que vi".
     *
     * El registro NO cambia: se sigue guardando cada visionado, así que las
     * estadísticas, el contador de vistas y el feed no se ven afectados.
     */
    public function scopeInDiary(Builder $query): Builder
    {
        return $query->where(
            fn (Builder $entry) => $entry->whereHas('review')->orWhere('is_rewatch', true)
        );
    }
}
