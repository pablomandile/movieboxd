<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PosterCard from '@/components/PosterCard.vue';
import RatingStars from '@/components/RatingStars.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated, TitleCard } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Eye } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type WatchedCard = TitleCard & { rating: number | null };

const props = defineProps<{
    titles: Paginated<WatchedCard>;
    type: 'movie' | 'tv' | null;
    counts: { all: number; movie: number; tv: number };
}>();

const { t } = useI18n();

// El filtro es server-side (la grilla está paginada), así que las solapas navegan
const tabs = computed(() => [
    { key: null, label: t('watched.all'), count: props.counts.all },
    { key: 'movie' as const, label: t('nav.films'), count: props.counts.movie },
    { key: 'tv' as const, label: t('nav.shows'), count: props.counts.tv },
]);
</script>

<template>
    <AppLayout>
        <Head :title="t('nav.watched')" />

        <div class="section-divider flex items-baseline justify-between">
            <h1 class="section-heading">{{ t('nav.watched') }}</h1>
            <span class="text-[11px] text-lb-muted">{{ counts.all }}</span>
        </div>

        <nav class="mt-4 flex flex-wrap gap-x-5 gap-y-1">
            <Link
                v-for="tab in tabs"
                :key="tab.key ?? 'all'"
                :href="route('watched.index', tab.key ? { type: tab.key } : {})"
                class="pb-1 text-[0.8125rem] font-bold uppercase tracking-[0.075em] transition-colors"
                :class="
                    type === tab.key
                        ? 'relative text-white after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-lb-green'
                        : 'text-lb-muted hover:text-lb-text'
                "
                :aria-current="type === tab.key ? 'page' : undefined"
            >
                {{ tab.label }}
                <span class="ml-1 font-normal text-lb-muted">{{ tab.count }}</span>
            </Link>
        </nav>

        <EmptyState v-if="!titles.data.length" :icon="Eye" :title="t('watched.empty')" :description="t('watched.emptyHint')">
            <Link
                :href="route('search')"
                class="inline-block rounded bg-lb-green-dark px-4 py-2 text-[0.75rem] font-bold uppercase tracking-[0.075em] text-white hover:bg-lb-green"
            >
                {{ t('search.title') }}
            </Link>
        </EmptyState>

        <div v-else class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
            <div v-for="card in titles.data" :key="`${card.type}-${card.tmdbId}`">
                <PosterCard :card="card" show-title />
                <!-- Reserva el alto siempre, para que las filas no queden desparejas -->
                <div class="mt-0.5 h-5">
                    <RatingStars v-if="card.rating" :model-value="card.rating" readonly size="sm" />
                </div>
            </div>
        </div>

        <SimplePagination :prev="titles.prev_page_url" :next="titles.next_page_url" />
    </AppLayout>
</template>
