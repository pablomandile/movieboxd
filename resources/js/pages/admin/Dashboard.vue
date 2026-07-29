<script setup lang="ts">
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    stats: {
        users: number;
        bannedUsers: number;
        movies: number;
        shows: number;
        diaryEntries: number;
        reviews: number;
        comments: number;
        lists: number;
        pendingReports: number;
    };
    recentUsers: { username: string; name: string; createdAt: string; isBanned: boolean }[];
}>();

const { t } = useI18n();

const tiles = [
    { key: 'users', value: props.stats.users },
    { key: 'movies', value: props.stats.movies },
    { key: 'shows', value: props.stats.shows },
    { key: 'diaryEntries', value: props.stats.diaryEntries },
    { key: 'reviews', value: props.stats.reviews },
    { key: 'comments', value: props.stats.comments },
    { key: 'lists', value: props.stats.lists },
    { key: 'bannedUsers', value: props.stats.bannedUsers },
] as const;
</script>

<template>
    <AdminLayout active="dashboard">
        <Head :title="t('admin.dashboard')" />

        <Link
            v-if="stats.pendingReports > 0"
            :href="route('admin.reports.index')"
            class="mb-6 block rounded bg-lb-orange/15 p-4 ring-1 ring-lb-orange/40"
        >
            <p class="text-sm font-semibold text-lb-orange">
                {{ t('admin.pendingReports', { count: stats.pendingReports }) }}
            </p>
        </Link>

        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <div v-for="tile in tiles" :key="tile.key" class="rounded bg-lb-panel p-4">
                <p class="text-2xl font-bold text-white">{{ tile.value }}</p>
                <p class="mt-0.5 text-[11px] uppercase tracking-wide text-lb-muted">{{ t(`admin.stats.${tile.key}`) }}</p>
            </div>
        </div>

        <div class="section-divider mt-10">
            <h2 class="section-heading">{{ t('admin.recentUsers') }}</h2>
        </div>
        <ul class="mt-3 divide-y divide-lb-line/30">
            <li v-for="user in recentUsers" :key="user.username" class="flex items-center gap-3 py-2 text-sm">
                <Link :href="route('profiles.show', user.username)" class="font-semibold text-white hover:text-lb-blue">
                    {{ user.name }}
                </Link>
                <span class="text-xs text-lb-muted">@{{ user.username }}</span>
                <span v-if="user.isBanned" class="rounded-sm bg-red-500/20 px-1.5 text-[10px] uppercase text-red-400">
                    {{ t('admin.banned') }}
                </span>
                <span class="ml-auto text-xs text-lb-muted">{{ user.createdAt }}</span>
            </li>
        </ul>
    </AdminLayout>
</template>
