<?php

namespace App\Services\Tmdb;

use Illuminate\Support\Str;

/**
 * Busca una película por nombre (y opcionalmente título alterno y año) para la
 * API, SIN importar nada ni adivinar: si hay una sola coincidencia exacta la
 * devuelve; si no, devuelve candidatos para que el usuario elija.
 *
 * Distinto de LetterboxdImportService::match, que se queda con el primer
 * resultado razonable porque importa cientos de películas sin nadie mirando.
 *
 * Cada nombre se busca en castellano latino y en inglés: el título que manda la
 * app de origen puede estar en cualquiera de los dos (o ser el original), y TMDB
 * devuelve el título en el idioma pedido. Se compara contra los tres.
 */
class MovieCandidates
{
    private const MAX_CANDIDATOS = 6;

    public function __construct(protected TmdbClient $tmdb) {}

    /**
     * @return array{status: 'matched'|'ambiguous'|'not_found', results: list<array<string, mixed>>}
     */
    public function find(string $title, ?string $alt = null, ?int $year = null): array
    {
        $consultas = array_values(array_unique(array_filter([trim($title), trim((string) $alt)])));
        $buscados = array_values(array_filter(array_map($this->normalizar(...), $consultas)));

        // tmdb_id => resultado en castellano + los títulos en los tres idiomas
        $porId = [];
        foreach ($consultas as $consulta) {
            foreach (['es-MX', 'en-US'] as $idioma) {
                foreach ($this->buscar($consulta, $year, $idioma) as $r) {
                    $id = $r['id'];
                    $porId[$id] ??= ['es' => null, 'nombres' => [], 'orden' => count($porId)];
                    if ($idioma === 'es-MX') {
                        $porId[$id]['es'] = $r;
                    }
                    $porId[$id]['nombres'][] = $this->normalizar((string) ($r['title'] ?? ''));
                    $porId[$id]['nombres'][] = $this->normalizar((string) ($r['original_title'] ?? ''));
                    $porId[$id]['fallback'] ??= $r;
                }
            }
        }

        if ($porId === []) {
            return ['status' => 'not_found', 'results' => []];
        }

        $candidatos = array_map(function (array $c) use ($buscados, $year) {
            $r = $c['es'] ?? $c['fallback'];
            $anio = $this->anio($r);

            return [
                'tmdb_id' => $r['id'],
                'title' => $r['title'] ?? $r['original_title'] ?? '',
                'original_title' => $r['original_title'] ?? null,
                'year' => $anio,
                'poster_url' => isset($r['poster_path']) ? "https://image.tmdb.org/t/p/w185{$r['poster_path']}" : null,
                'overview' => Str::limit((string) ($r['overview'] ?? ''), 220),
                '_exacta' => array_intersect($buscados, $c['nombres']) !== [],
                '_anio_ok' => $year === null || ($anio !== null && abs($anio - $year) <= 1),
                '_popularidad' => (float) ($r['popularity'] ?? 0),
                '_orden' => $c['orden'],
            ];
        }, array_values($porId));

        $exactas = array_values(array_filter($candidatos, fn ($c) => $c['_exacta'] && $c['_anio_ok']));
        if (count($exactas) === 1) {
            return ['status' => 'matched', 'results' => [$this->limpiar($exactas[0])]];
        }

        usort($candidatos, fn ($a, $b) => [$b['_exacta'] && $b['_anio_ok'], $b['_anio_ok'], $b['_popularidad']]
            <=> [$a['_exacta'] && $a['_anio_ok'], $a['_anio_ok'], $a['_popularidad']]);

        return [
            'status' => 'ambiguous',
            'results' => array_map($this->limpiar(...), array_slice($candidatos, 0, self::MAX_CANDIDATOS)),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function buscar(string $consulta, ?int $year, string $idioma): array
    {
        $resultados = $this->tmdb->searchMovie($consulta, $year, 1, $idioma)['results'] ?? [];

        // El año que dicen en el podcast puede ser el del estreno local: si con el
        // año exacto no aparece nada, se busca sin año y el ±1 se aplica después.
        if ($resultados === [] && $year !== null) {
            $resultados = $this->tmdb->searchMovie($consulta, null, 1, $idioma)['results'] ?? [];
        }

        return $resultados;
    }

    private function anio(array $r): ?int
    {
        $fecha = (string) ($r['release_date'] ?? '');

        return $fecha === '' ? null : (int) substr($fecha, 0, 4);
    }

    /** Minúsculas, sin acentos ni signos: «¡Relatos salvajes!» = «relatos salvajes». */
    private function normalizar(string $texto): string
    {
        $texto = Str::lower(Str::ascii($texto));
        $texto = preg_replace('/[^a-z0-9]+/', ' ', $texto) ?? '';

        return trim($texto);
    }

    private function limpiar(array $c): array
    {
        return array_filter($c, fn ($k) => ! str_starts_with($k, '_'), ARRAY_FILTER_USE_KEY);
    }
}
