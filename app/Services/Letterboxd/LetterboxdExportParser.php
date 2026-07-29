<?php

namespace App\Services\Letterboxd;

use RuntimeException;
use ZipArchive;

/**
 * Convierte el ZIP de exportación de Letterboxd (formato v7) en payloads
 * agregados por película única. Cada film del resultado junta TODO lo que
 * el export dice de esa película: watched, rating, like, watchlist,
 * entradas de diario (con reseña) y membresías de listas.
 *
 * Los CSV usan comillas para campos con comas y saltos de línea (las
 * reseñas son multilínea): siempre fgetcsv, nunca split por líneas.
 */
class LetterboxdExportParser
{
    /**
     * @param  array<string, bool>  $options  secciones: watched, diary, watchlist, likes, lists
     * @return array{films: array<string, array>, lists: array<int, array>}
     */
    public function parse(string $zipPath, array $options): array
    {
        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException("No se pudo abrir el ZIP: {$zipPath}");
        }

        try {
            $entries = $this->mapEntries($zip);

            $films = [];
            $lists = [];

            if (($options['watched'] ?? false) && isset($entries['watched'])) {
                foreach ($this->readCsv($zip, $entries['watched']) as $row) {
                    $film = &$this->film($films, $row['Name'] ?? '', $row['Year'] ?? null);
                    $film['watched_at'] = $row['Date'] ?? null;
                    unset($film);
                }
            }

            if (($options['watched'] ?? false) && isset($entries['ratings'])) {
                foreach ($this->readCsv($zip, $entries['ratings']) as $row) {
                    if (! isset($row['Rating']) || $row['Rating'] === '') {
                        continue;
                    }

                    $film = &$this->film($films, $row['Name'] ?? '', $row['Year'] ?? null);
                    $film['rating'] = (int) round(((float) $row['Rating']) * 2);
                    unset($film);
                }
            }

            if (($options['likes'] ?? false) && isset($entries['likes'])) {
                foreach ($this->readCsv($zip, $entries['likes']) as $row) {
                    $film = &$this->film($films, $row['Name'] ?? '', $row['Year'] ?? null);
                    $film['liked_at'] = $row['Date'] ?? null;
                    unset($film);
                }
            }

            if (($options['watchlist'] ?? false) && isset($entries['watchlist'])) {
                foreach ($this->readCsv($zip, $entries['watchlist']) as $row) {
                    $film = &$this->film($films, $row['Name'] ?? '', $row['Year'] ?? null);
                    $film['watchlist_at'] = $row['Date'] ?? null;
                    unset($film);
                }
            }

            if ($options['diary'] ?? false) {
                $this->parseDiary($zip, $entries, $films);
            }

            if ($options['lists'] ?? false) {
                foreach ($entries['lists'] ?? [] as $listEntry) {
                    $parsed = $this->parseList($zip, $listEntry, $films);

                    if ($parsed !== null) {
                        $lists[] = $parsed;
                    }
                }
            }

            return ['films' => $films, 'lists' => $lists];
        } finally {
            $zip->close();
        }
    }

    /**
     * Ubica los CSV dentro del ZIP por sufijo de ruta: el export puede venir
     * con o sin carpeta raíz. Ignora deleted/ y orphaned/.
     *
     * @return array<string, mixed>
     */
    protected function mapEntries(ZipArchive $zip): array
    {
        $entries = ['lists' => []];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = str_replace('\\', '/', (string) $zip->getNameIndex($i));
            $lower = strtolower($name);

            if (str_contains($lower, 'deleted/') || str_contains($lower, 'orphaned/')) {
                continue;
            }

            match (true) {
                str_ends_with($lower, '/watched.csv') || $lower === 'watched.csv' => $entries['watched'] = $name,
                str_ends_with($lower, '/ratings.csv') || $lower === 'ratings.csv' => $entries['ratings'] = $name,
                str_ends_with($lower, '/diary.csv') || $lower === 'diary.csv' => $entries['diary'] = $name,
                str_ends_with($lower, '/reviews.csv') || $lower === 'reviews.csv' => $entries['reviews'] = $name,
                str_ends_with($lower, '/watchlist.csv') || $lower === 'watchlist.csv' => $entries['watchlist'] = $name,
                str_ends_with($lower, 'likes/films.csv') => $entries['likes'] = $name,
                str_contains($lower, 'lists/') && str_ends_with($lower, '.csv') => $entries['lists'][] = $name,
                default => null,
            };
        }

        return $entries;
    }

    /**
     * reviews.csv y diary.csv repiten las mismas entradas (reviews es el
     * subconjunto con texto): primero las reviews, después el diario
     * deduplicando por name|year|watched_date.
     */
    protected function parseDiary(ZipArchive $zip, array $entries, array &$films): void
    {
        $seen = [];

        if (isset($entries['reviews'])) {
            foreach ($this->readCsv($zip, $entries['reviews']) as $row) {
                $record = $this->diaryRecord($row);
                $key = $this->filmKey($row['Name'] ?? '', $row['Year'] ?? null).'|'.$record['watched_on'];
                $seen[$key] = true;

                $film = &$this->film($films, $row['Name'] ?? '', $row['Year'] ?? null);
                $film['diary'][] = $record;
                unset($film);
            }
        }

        if (isset($entries['diary'])) {
            foreach ($this->readCsv($zip, $entries['diary']) as $row) {
                $record = $this->diaryRecord($row);
                $key = $this->filmKey($row['Name'] ?? '', $row['Year'] ?? null).'|'.$record['watched_on'];

                if (isset($seen[$key])) {
                    continue;
                }

                $film = &$this->film($films, $row['Name'] ?? '', $row['Year'] ?? null);
                $film['diary'][] = $record;
                unset($film);
            }
        }
    }

    protected function diaryRecord(array $row): array
    {
        $tags = array_values(array_filter(array_map('trim', explode(',', (string) ($row['Tags'] ?? '')))));

        return [
            'watched_on' => ($row['Watched Date'] ?? '') ?: ($row['Date'] ?? now()->toDateString()),
            'rating' => isset($row['Rating']) && $row['Rating'] !== '' ? (int) round(((float) $row['Rating']) * 2) : null,
            'rewatch' => strcasecmp((string) ($row['Rewatch'] ?? ''), 'Yes') === 0,
            'tags' => $tags,
            'review' => ($row['Review'] ?? '') !== '' ? $row['Review'] : null,
        ];
    }

    /**
     * Una lista tiene dos bloques CSV: metadatos (fila después del primer
     * header) e ítems (después del header que arranca con "Position").
     */
    protected function parseList(ZipArchive $zip, string $entry, array &$films): ?array
    {
        $rows = $this->readRawRows($zip, $entry);

        $meta = null;
        $itemsHeaderIndex = null;

        foreach ($rows as $index => $row) {
            if ($meta === null && ($row[0] ?? '') === 'Date' && in_array('URL', $row, true)) {
                $metaRow = $rows[$index + 1] ?? null;

                if ($metaRow !== null) {
                    $meta = array_combine($row, array_pad(array_slice($metaRow, 0, count($row)), count($row), null));
                }
            }

            if (($row[0] ?? '') === 'Position') {
                $itemsHeaderIndex = $index;
                break;
            }
        }

        if ($meta === null || ($meta['Name'] ?? '') === '') {
            return null;
        }

        $items = [];

        if ($itemsHeaderIndex !== null) {
            $header = $rows[$itemsHeaderIndex];

            foreach (array_slice($rows, $itemsHeaderIndex + 1) as $row) {
                if (($row[0] ?? '') === '') {
                    continue;
                }

                $item = array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));

                $items[] = [
                    'position' => (int) $item['Position'],
                    'name' => $item['Name'],
                    'year' => $item['Year'] !== null && $item['Year'] !== '' ? (int) $item['Year'] : null,
                ];

                // La membresía también se registra en el payload del film
                $film = &$this->film($films, $item['Name'] ?? '', $item['Year'] ?? null);
                $film['in_lists'] = true;
                unset($film);
            }
        }

        return [
            'name' => $meta['Name'],
            'description' => ($meta['Description'] ?? '') ?: null,
            'items' => $items,
        ];
    }

    /**
     * Referencia al payload del film, creándolo si no existe.
     */
    protected function &film(array &$films, string $name, mixed $year): array
    {
        $key = $this->filmKey($name, $year);

        if (! isset($films[$key])) {
            $films[$key] = [
                'name' => trim($name),
                'year' => $year !== null && $year !== '' ? (int) $year : null,
            ];
        }

        return $films[$key];
    }

    public function filmKey(string $name, mixed $year): string
    {
        $year = $year !== null && $year !== '' ? (int) $year : '';

        return mb_strtolower(trim($name)).'|'.$year;
    }

    /**
     * Lee un CSV con header y devuelve filas asociativas.
     *
     * @return iterable<array<string, string|null>>
     */
    protected function readCsv(ZipArchive $zip, string $entry): iterable
    {
        $rows = $this->readRawRows($zip, $entry);
        $header = array_shift($rows);

        if ($header === null) {
            return;
        }

        foreach ($rows as $row) {
            if ($row === [null] || ($row[0] ?? '') === '' && count($row) === 1) {
                continue;
            }

            yield array_combine($header, array_pad(array_slice($row, 0, count($header)), count($header), null));
        }
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    protected function readRawRows(ZipArchive $zip, string $entry): array
    {
        $content = $zip->getFromName($entry);

        if ($content === false) {
            return [];
        }

        // BOM de UTF-8
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $content);
        rewind($stream);

        $rows = [];

        while (($row = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
            $rows[] = $row;
        }

        fclose($stream);

        return $rows;
    }
}
