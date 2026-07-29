<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PosterCard from '@/components/PosterCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated, TitleCard } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    titles: Paginated<TitleCard>;
}>();

const { t } = useI18n();
</script>

<template>
    <AppLayout>
        <Head :title="t('nav.watchlist')" />

        <div class="section-divider flex items-baseline justify-between">
            <h1 class="section-heading">{{ t('nav.watchlist') }}</h1>
            <span class="text-[11px] text-lb-muted">{{ titles.total }}</span>
        </div>

        <EmptyState v-if="!titles.data.length" :icon="Clock" :title="t('watchlist.empty')" :description="t('watchlist.emptyHint')">
            <Link
                :href="route('search')"
                class="inline-block rounded bg-lb-green-dark px-4 py-2 text-[0.75rem] font-bold uppercase tracking-[0.075em] text-white hover:bg-lb-green"
            >
                {{ t('search.title') }}
            </Link>
        </EmptyState>

        <div v-else class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
            <PosterCard v-for="card in titles.data" :key="`${card.type}-${card.tmdbId}`" :card="card" show-title />
        </div>

        <div v-if="titles.prev_page_url || titles.next_page_url" class="mt-8 flex justify-center gap-4">
            <Link
                v-if="titles.prev_page_url"
                :href="titles.prev_page_url"
                class="rounded bg-lb-surface px-4 py-2 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-lb-text hover:text-white"
            >
                ← {{ t('search.previous') }}
            </Link>
            <Link
                v-if="titles.next_page_url"
                :href="titles.next_page_url"
                class="rounded bg-lb-surface px-4 py-2 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-lb-text hover:text-white"
            >
                {{ t('search.next') }} →
            </Link>
        </div>
    </AppLayout>
</template>
