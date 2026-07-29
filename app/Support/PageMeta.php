<?php

namespace App\Support;

/**
 * Metadatos Open Graph por página. Se comparten como prop `meta` de Inertia
 * y los renderiza server-side el template Blade raíz (sin SSR, el <Head> de
 * Inertia solo existe en el cliente y los crawlers no lo ven).
 */
class PageMeta
{
    public static function make(?string $title, ?string $description = null, ?string $imagePath = null, string $type = 'website'): array
    {
        return [
            'title' => $title,
            'description' => $description ? str($description)->limit(200)->toString() : null,
            'image' => $imagePath ? "https://image.tmdb.org/t/p/w500{$imagePath}" : null,
            'type' => $type,
        ];
    }
}
