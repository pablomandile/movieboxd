<script setup lang="ts">
import FollowButton from '@/components/FollowButton.vue';
import PosterCard from '@/components/PosterCard.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import type { SharedData, TitleCard } from '@/types';
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

export interface ProfileData {
    name: string;
    username: string;
    avatarPath: string | null;
    bio: string | null;
    counts: { watched: number; thisYear: number; followers: number; following: number };
    favorites: TitleCard[];
    isOwn: boolean;
    isFollowing: boolean;
}

const props = defineProps<{
    profile: ProfileData;
    activeTab: string;
}>();

const { t } = useI18n();
const page = usePage<SharedData>();

const tabs = computed(() => [
    { key: 'films', label: t('profile.tabs.films'), href: route('profiles.show', props.profile.username) },
    { key: 'diary', label: t('profile.tabs.diary'), href: route('profiles.tab', { user: props.profile.username, tab: 'diary' }) },
    { key: 'reviews', label: t('profile.tabs.reviews'), href: route('profiles.tab', { user: props.profile.username, tab: 'reviews' }) },
    { key: 'watchlist', label: t('profile.tabs.watchlist'), href: route('profiles.tab', { user: props.profile.username, tab: 'watchlist' }) },
    { key: 'lists', label: t('profile.tabs.lists'), href: route('profiles.tab', { user: props.profile.username, tab: 'lists' }) },
    { key: 'likes', label: t('profile.tabs.likes'), href: route('profiles.tab', { user: props.profile.username, tab: 'likes' }) },
    { key: 'network', label: t('profile.tabs.network'), href: route('profiles.tab', { user: props.profile.username, tab: 'network' }) },
    { key: 'stats', label: t('profile.tabs.stats'), href: route('profiles.tab', { user: props.profile.username, tab: 'stats' }) },
]);
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center gap-4">
            <UserAvatar :user="{ name: profile.name, avatar_path: profile.avatarPath }" size="lg" />
            <div class="min-w-0 flex-1">
                <h1 class="text-xl font-bold text-white">
                    {{ profile.name }}
                    <span class="ml-1 text-sm font-normal text-lb-muted">@{{ profile.username }}</span>
                </h1>
                <p v-if="profile.bio" class="mt-0.5 max-w-xl text-sm text-lb-text">{{ profile.bio }}</p>
            </div>

            <FollowButton v-if="page.props.auth.user && !profile.isOwn" :username="profile.username" :is-following="profile.isFollowing" />

            <div class="flex gap-5 text-center">
                <div>
                    <p class="text-lg font-bold text-white">{{ profile.counts.watched }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-lb-muted">{{ t('profile.stats.watched') }}</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-white">{{ profile.counts.thisYear }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-lb-muted">{{ t('profile.stats.thisYear') }}</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-white">{{ profile.counts.followers }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-lb-muted">{{ t('profile.stats.followers') }}</p>
                </div>
                <div>
                    <p class="text-lg font-bold text-white">{{ profile.counts.following }}</p>
                    <p class="text-[10px] uppercase tracking-wide text-lb-muted">{{ t('profile.stats.following') }}</p>
                </div>
            </div>
        </div>

        <!-- 4 favoritos destacados -->
        <template v-if="profile.favorites.length">
            <div class="section-divider mt-8">
                <h2 class="section-heading">{{ t('profile.favorites') }}</h2>
            </div>
            <div class="mt-3 grid max-w-md grid-cols-4 gap-3">
                <PosterCard v-for="card in profile.favorites" :key="card.tmdbId" :card="card" />
            </div>
        </template>

        <!-- Tabs -->
        <nav class="mt-8 flex gap-1 overflow-x-auto rounded bg-lb-surface p-1">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href"
                class="relative whitespace-nowrap rounded px-3 py-2 text-[0.9375rem] text-lb-text transition-colors hover:text-white"
                :class="{
                    'text-white after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:bg-lb-green': tab.key === activeTab,
                }"
            >
                {{ tab.label }}
            </Link>
        </nav>
    </div>
</template>
