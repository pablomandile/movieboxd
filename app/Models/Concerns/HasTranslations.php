<?php

namespace App\Models\Concerns;

trait HasTranslations
{
    /**
     * Devuelve el campo traducido según el locale activo, con fallback
     * al valor base almacenado (inglés).
     */
    public function localized(string $field): ?string
    {
        $locale = app()->getLocale();

        return data_get($this->translations, "{$locale}.{$field}")
            ?: $this->getAttribute($field);
    }

    public function getLocalizedTitleAttribute(): ?string
    {
        return $this->localized($this->titleField());
    }

    public function getLocalizedOverviewAttribute(): ?string
    {
        return $this->localized('overview');
    }

    protected function titleField(): string
    {
        return 'title';
    }
}
