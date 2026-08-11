<script setup lang="ts">
import FeedItem, { type FeedItemData } from '@/components/FeedItem.vue';
import PosterCard from '@/components/PosterCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData, TitleCard } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    trending: TitleCard[];
    trendingMovies: TitleCard[];
    trendingShows: TitleCard[];
    feed: FeedItemData[];
}>();

const { t } = useI18n();
const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);

// Solapas: tendencias mezcladas, solo películas o solo series
type Tab = 'trending' | 'movies' | 'shows';
const activeTab = ref<Tab>('trending');

const tabs = computed<{ key: Tab; label: string }[]>(() => [
    { key: 'trending', label: t('home.trending') },
    { key: 'movies', label: t('nav.films') },
    { key: 'shows', label: t('nav.shows') },
]);

const visibleCards = computed(() =>
    activeTab.value === 'movies' ? props.trendingMovies : activeTab.value === 'shows' ? props.trendingShows : props.trending,
);
</script>

<template>
    <AppLayout>
        <Head :title="t('nav.home')" />

        <section v-if="!user" class="py-12 text-center sm:py-16">
            <h1 class="mx-auto max-w-3xl text-3xl font-extrabold leading-tight text-white sm:text-5xl">
                {{ t('app.tagline') }}
            </h1>
            <div class="mt-8">
                <Link
                    :href="route('register')"
                    class="inline-block rounded bg-lb-green-dark px-6 py-3 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-white transition-colors hover:bg-lb-green"
                >
                    {{ t('auth.createAccount') }}
                </Link>
            </div>
        </section>

        <section v-if="user" class="mt-4">
            <div class="section-divider">
                <h2 class="section-heading">{{ t('feed.title') }}</h2>
            </div>
            <ul v-if="feed.length" class="divide-y divide-lb-line/30">
                <FeedItem v-for="item in feed" :key="item.id" :item="item" />
            </ul>
            <p v-else class="mt-3 text-sm text-lb-muted">{{ t('feed.empty') }}</p>
        </section>

        <section class="mt-8">
            <!-- Solapas con el estilo de heading de sección -->
            <div class="section-divider flex flex-wrap items-baseline gap-x-5 gap-y-1" role="tablist">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    type="button"
                    role="tab"
                    :aria-selected="activeTab === tab.key"
                    class="section-heading relative pb-1 transition-colors"
                    :class="
                        activeTab === tab.key
                            ? 'text-white after:absolute after:inset-x-0 after:bottom-0 after:h-0.5 after:bg-lb-green'
                            : 'text-lb-muted hover:text-lb-text'
                    "
                    @click="activeTab = tab.key"
                >
                    {{ tab.label }}
                </button>
            </div>

            <div v-if="visibleCards.length" class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                <PosterCard v-for="card in visibleCards" :key="`${card.type}-${card.tmdbId}`" :card="card" show-title />
            </div>
            <p v-else class="mt-6 text-sm text-lb-muted">{{ t('home.emptyTrending') }}</p>
        </section>
    </AppLayout>
</template>
