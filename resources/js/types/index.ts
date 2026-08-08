import type { LucideIcon } from 'lucide-vue-next';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavItem {
    title: string;
    href: string;
    icon?: LucideIcon;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    locale: 'es' | 'en';
    features: { comments: boolean; lists: boolean; reviews: boolean; registration: boolean };
    oauth: { google: boolean };
    auth: Auth;
    ziggy: {
        location: string;
        url: string;
        port: null | number;
        defaults: Record<string, unknown>;
        routes: Record<string, string>;
    };
}

export interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    avatar_path: string | null;
    role: 'user' | 'admin';
    locale: 'es' | 'en';
    email_verified_at: string | null;
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface TitleCard {
    type: 'movie' | 'tv';
    tmdbId: number;
    title: string;
    posterPath: string | null;
    year: number | null;
    url: string;
}

export interface CastMember {
    tmdb_id: number;
    name: string;
    character?: string | null;
    profile_path?: string | null;
}

export interface CrewMember {
    tmdb_id: number;
    name: string;
    profile_path?: string | null;
}

export interface TitleDetail {
    id: number;
    type: 'movie' | 'tv';
    slug: string;
    tmdbId: number;
    title: string;
    originalTitle: string | null;
    tagline: string | null;
    overview: string | null;
    posterPath: string | null;
    backdropPath: string | null;
    year: number | null;
    releaseDate: string | null;
    runtime: number | null;
    genres: { id: number; name: string }[];
    credits: { cast?: CastMember[]; directors?: CrewMember[]; creators?: CrewMember[] };
    originalLanguage: string | null;
    tvStatus: string | null;
    seasonsCount: number | null;
    episodesCount: number | null;
    counts: { watched: number; likes: number; watchlist: number; reviews: number };
    ratings: { count: number; average: number | null; histogram: Record<string, number> | null };
}

export interface SeasonSummary {
    id: number;
    number: number;
    name: string;
    posterPath: string | null;
    airDate: string | null;
    episodesCount: number;
}

export interface EpisodeSummary {
    id: number;
    number: number;
    code: string;
    name: string;
    overview: string | null;
    stillPath: string | null;
    airDate: string | null;
    runtime: number | null;
}

export interface TitleViewer {
    lists: { id: number; name: string; hasTitle: boolean }[];
    isFavorite: boolean;
    watched: boolean;
    liked: boolean;
    inWatchlist: boolean;
    rating: number | null;
    hasLogged: boolean;
}

export interface SeasonViewer {
    watchedEpisodeIds: number[];
    rating: number | null;
    hasLogged: boolean;
}

export interface EpisodeViewer {
    watched: boolean;
    rating: number | null;
    hasLogged: boolean;
}

export interface Loggable {
    type: 'title' | 'season' | 'episode';
    id: number;
    name: string;
}

export interface DiaryEntryItem {
    id: number;
    watchedOn: string;
    rating: number | null;
    isRewatch: boolean;
    tags: string[];
    type: 'title' | 'season' | 'episode';
    name: string;
    context: string | null;
    url: string;
    posterPath: string | null;
}

export interface ReviewItem {
    id: number;
    body: string;
    containsSpoilers: boolean;
    likesCount: number;
    commentsCount: number;
    createdAt: string;
    rating: number | null;
    watchedOn: string | null;
    user: { name: string; username: string; avatar_path: string | null };
    isOwn: boolean;
    likedByViewer: boolean;
    url: string;
}

export interface CommentItem {
    id: number;
    body: string;
    createdAt: string;
    user: { name: string; username: string; avatar_path: string | null };
    canDelete: boolean;
}

export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
}
