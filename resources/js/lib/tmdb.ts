const TMDB_IMAGE_BASE = 'https://image.tmdb.org/t/p/';

export type TmdbImageSize = 'w92' | 'w154' | 'w185' | 'w300' | 'w342' | 'w500' | 'w780' | 'w1280' | 'original';

export function tmdbImage(path: string | null | undefined, size: TmdbImageSize = 'w342'): string | null {
    return path ? `${TMDB_IMAGE_BASE}${size}${path}` : null;
}
