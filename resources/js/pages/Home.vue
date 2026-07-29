<script setup lang="ts">
import FeedItem, { type FeedItemData } from '@/components/FeedItem.vue';
import PosterCard from '@/components/PosterCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { SharedData, TitleCard } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    trending: TitleCard[];
    feed: FeedItemData[];
}>();

const { t } = useI18n();
const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
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
            <div class="section-divider flex items-baseline justify-between">
                <h2 class="section-heading">{{ t('home.trending') }}</h2>
            </div>

            <div v-if="trending.length" class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                <PosterCard v-for="card in trending" :key="`${card.type}-${card.tmdbId}`" :card="card" show-title />
            </div>
            <p v-else class="mt-6 text-sm text-lb-muted">{{ t('home.emptyTrending') }}</p>
        </section>
    </AppLayout>
</template>
