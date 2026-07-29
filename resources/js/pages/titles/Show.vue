<script setup lang="ts">
import ProgressBar from '@/components/ProgressBar.vue';
import ReviewsSection from '@/components/ReviewsSection.vue';
import TitleDetails from '@/components/TitleDetails.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { tmdbImage } from '@/lib/tmdb';
import type { ReviewItem, SeasonSummary, TitleDetail, TitleViewer } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
    title: TitleDetail;
    viewer: TitleViewer | null;
    seasons: (SeasonSummary & { watchedCount: number })[];
    reviews?: ReviewItem[];
}>();

const { t } = useI18n();
</script>

<template>
    <AppLayout>
        <Head :title="title.title" />

        <TitleDetails :title="title" :viewer="viewer">
            <template v-if="seasons.length">
                <div class="section-divider mt-8">
                    <h2 class="section-heading">{{ t('titles.seasons') }}</h2>
                </div>
                <div class="mt-3 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                    <Link
                        v-for="season in seasons"
                        :key="season.id"
                        :href="route('seasons.show', { title: title.slug, seasonNumber: season.number })"
                        class="group block"
                    >
                        <div
                            class="aspect-[2/3] overflow-hidden rounded bg-lb-surface shadow-[inset_0_0_0_1px_rgba(221,238,255,0.075)] transition-shadow group-hover:shadow-[inset_0_0_0_3px_#00E054]"
                        >
                            <img
                                v-if="season.posterPath"
                                :src="tmdbImage(season.posterPath, 'w342')!"
                                :alt="season.name"
                                loading="lazy"
                                class="h-full w-full object-cover"
                            />
                            <div v-else class="flex h-full items-center justify-center p-2 text-center text-xs text-lb-muted">
                                {{ season.name }}
                            </div>
                        </div>
                        <p class="mt-1.5 truncate text-xs text-lb-text">{{ season.name }}</p>
                        <p class="text-[11px] text-lb-muted">
                            <template v-if="viewer">{{ season.watchedCount }}/</template>{{ season.episodesCount }}
                            {{ t('titles.episodes') }}
                        </p>
                        <ProgressBar v-if="viewer" class="mt-1" :watched="season.watchedCount" :total="season.episodesCount" />
                    </Link>
                </div>
            </template>
            <ReviewsSection :reviews="reviews" />
        </TitleDetails>
    </AppLayout>
</template>
