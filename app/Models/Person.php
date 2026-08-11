<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Actor, director o miembro del equipo. Igual que Title: snapshot local
 * importado on demand desde TMDB la primera vez que se visita.
 */
class Person extends Model
{
    use HasFactory;

    protected $table = 'people';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'translations' => 'array',
            'credits' => 'array',
            'birthday' => 'date',
            'deathday' => 'date',
            'popularity' => 'float',
            'synced_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** Biografía en el idioma activo, con fallback al inglés almacenado. */
    public function getLocalizedBiographyAttribute(): ?string
    {
        return data_get($this->translations, app()->getLocale().'.biography') ?: $this->biography;
    }

    /**
     * Las personas cambian poco (alguna película nueva, un fallecimiento):
     * un umbral más largo que el de los títulos alcanza.
     */
    public function isStale(): bool
    {
        return $this->synced_at === null || $this->synced_at->lt(now()->subDays(30));
    }
}
