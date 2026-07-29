<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import ProfileHeader, { type ProfileData } from '@/components/ProfileHeader.vue';
import RatingStars from '@/components/RatingStars.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { tmdbImage } from '@/lib/tmdb';
import type { DiaryEntryItem, Paginated } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { CalendarDays, Repeat } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    profile: ProfileData;
    entries: Paginated<DiaryEntryItem>;
    year: number | null;
    years: number[];
}>();

const { t } = useI18n();

const tabHref = (year: number | null) =>
    route('profiles.tab', {
        user: props.profile.username,
        tab: 'diary',
        ...(year ? { year } : {}),
    });
</script>

<template>
    <AppLayout>
        <Head :title="`${profile.name} — ${t('profile.tabs.diary')}`" />
        <ProfileHeader :profile="profile" active-tab="diary" />

        <!-- Navegación por año -->
        <div v-if="years.length > 1" class="mt-6 flex flex-wrap gap-1">
            <Link
                :href="tabHref(null)"
                class="rounded px-2.5 py-1 text-xs font-semibold"
                :class="year === null ? 'bg-lb-green-dark text-white' : 'bg-lb-surface text-lb-text hover:text-white'"
            >
                {{ t('diary.allYears') }}
            </Link>
            <Link
                v-for="value in years"
                :key="value"
                :href="tabHref(value)"
                class="rounded px-2.5 py-1 text-xs font-semibold tabular-nums"
                :class="value === year ? 'bg-lb-green-dark text-white' : 'bg-lb-surface text-lb-text hover:text-white'"
            >
                {{ value }}
            </Link>
        </div>

        <EmptyState v-if="!entries.data.length" :icon="CalendarDays" :title="t('profile.emptyDiary')" />

        <ul v-else class="mt-6 divide-y divide-lb-line/30">
            <li v-for="entry in entries.data" :key="entry.id" class="flex items-center gap-4 py-3">
                <span class="w-20 shrink-0 text-xs tabular-nums text-lb-muted">{{ entry.watchedOn }}</span>
                <Link :href="entry.url" class="h-[54px] w-9 shrink-0 overflow-hidden rounded bg-lb-surface">
                    <img
                        v-if="entry.posterPath"
                        :src="tmdbImage(entry.posterPath, 'w92')!"
                        :alt="entry.name"
                        loading="lazy"
                        class="h-full w-full object-cover"
                    />
                </Link>
                <div class="min-w-0 flex-1">
                    <Link :href="entry.url" class="font-semibold text-white hover:text-lb-blue">{{ entry.name }}</Link>
                    <p v-if="entry.context" class="truncate text-xs text-lb-muted">{{ entry.context }}</p>
                    <div class="mt-0.5 flex items-center gap-2">
                        <RatingStars v-if="entry.rating" :model-value="entry.rating" readonly size="sm" />
                        <Repeat v-if="entry.isRewatch" class="size-3.5 text-lb-muted" :title="t('diary.rewatch')" />
                        <span v-for="tag in entry.tags" :key="tag" class="rounded-sm bg-lb-surface px-1.5 text-[10px] text-lb-muted">
                            {{ tag }}
                        </span>
                    </div>
                </div>
            </li>
        </ul>

        <SimplePagination :prev="entries.prev_page_url" :next="entries.next_page_url" />
    </AppLayout>
</template>
