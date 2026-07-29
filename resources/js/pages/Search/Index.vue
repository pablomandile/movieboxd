<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PosterCard from '@/components/PosterCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { TitleCard } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Search, SearchX } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    query: string;
    results: TitleCard[];
    page: number;
    totalPages: number;
}>();

const { t } = useI18n();
const input = ref(props.query);

function submit() {
    if (!input.value.trim()) return;
    router.get(route('search'), { q: input.value.trim() });
}

function goToPage(page: number) {
    router.get(route('search'), { q: props.query, page });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('search.title')" />

        <form @submit.prevent="submit" class="mx-auto flex max-w-xl gap-2">
            <div class="relative flex-1">
                <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-lb-muted" />
                <input
                    v-model="input"
                    type="search"
                    :placeholder="t('search.placeholder')"
                    autofocus
                    class="h-11 w-full rounded border-0 bg-lb-surface pl-10 pr-3 text-white placeholder:text-lb-muted focus:outline-none focus:ring-2 focus:ring-lb-blue/70"
                />
            </div>
            <button
                type="submit"
                class="rounded bg-lb-green-dark px-4 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-white hover:bg-lb-green"
            >
                {{ t('search.title') }}
            </button>
        </form>

        <section v-if="query" class="mt-10">
            <div class="section-divider">
                <h2 class="section-heading">{{ t('search.resultsFor', { query }) }}</h2>
            </div>

            <div v-if="results.length" class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                <PosterCard v-for="card in results" :key="`${card.type}-${card.tmdbId}`" :card="card" show-title />
            </div>
            <EmptyState v-else :icon="SearchX" :title="t('search.noResults', { query })" />

            <div v-if="totalPages > 1" class="mt-8 flex items-center justify-center gap-4">
                <button
                    v-if="page > 1"
                    @click="goToPage(page - 1)"
                    class="rounded bg-lb-surface px-4 py-2 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-lb-text hover:text-white"
                >
                    ← {{ t('search.previous') }}
                </button>
                <span class="text-sm text-lb-muted">{{ page }} / {{ totalPages }}</span>
                <button
                    v-if="page < totalPages"
                    @click="goToPage(page + 1)"
                    class="rounded bg-lb-surface px-4 py-2 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-lb-text hover:text-white"
                >
                    {{ t('search.next') }} →
                </button>
            </div>
        </section>
    </AppLayout>
</template>
