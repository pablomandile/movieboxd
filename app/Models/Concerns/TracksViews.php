<?php

namespace App\Models\Concerns;

/**
 * Anota la última visita a la ficha (columna viewed_at). Escribe como mucho una
 * vez por día y por fila, sin tocar updated_at ni disparar observers.
 */
trait TracksViews
{
    public function markViewed(): void
    {
        if ($this->viewed_at !== null && $this->viewed_at->gt(now()->subDay())) {
            return;
        }

        $this->viewed_at = now();
        $this->syncOriginalAttribute('viewed_at');

        static::query()->whereKey($this->getKey())->toBase()->update(['viewed_at' => $this->viewed_at]);
    }
}
