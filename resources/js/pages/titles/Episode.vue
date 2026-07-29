<script setup lang="ts">
import LogModal from '@/components/LogModal.vue';
import RatingStars from '@/components/RatingStars.vue';
import ReviewsSection from '@/components/ReviewsSection.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { tmdbImage } from '@/lib/tmdb';
import type { EpisodeSummary, EpisodeViewer, ReviewItem, SeasonSummary } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Circle } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    show: { slug: string; title: string; year: number | null; posterPath: string | null; backdropPath: string | null };
    season: SeasonSummary;
    episode: EpisodeSummary & { ratings: { count: number; average: number | null } };
    viewer: EpisodeViewer | null;
    reviews?: ReviewItem[];
}>();

const { t } = useI18n();
const logOpen = ref(false);

function toggleWatched() {
    router.post(route('episodes.watch', props.episode.id), {}, { preserveScroll: true });
}

function rate(value: number | null) {
    router.put(route('ratings.upsert'), { rateable_type: 'episode', rateable_id: props.episode.id, value }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="`${show.title} – ${episode.code} ${episode.name}`" />

        <div class="mx-auto max-w-3xl">
            <p class="text-sm">
                <Link :href="route('shows.show', show.slug)" class="font-semibold text-lb-text hover:text-lb-blue">
                    {{ show.title }}
                </Link>
                <span class="text-lb-muted"> / </span>
                <Link
                    :href="route('seasons.show', { title: show.slug, seasonNumber: season.number })"
                    class="font-semibold text-lb-text hover:text-lb-blue"
                >
                    {{ season.name }}
                </Link>
            </p>

            <h1 class="mt-2 font-serif text-3xl font-bold text-white">
                <span class="mr-2 align-middle font-sans text-base font-semibold uppercase tracking-wide text-lb-muted">{{ episode.code }}</span>
                {{ episode.name }}
            </h1>

            <p class="mt-1 text-xs text-lb-muted">
                <span v-if="episode.airDate">{{ t('titles.airDate') }}: {{ episode.airDate }}</span>
                <span v-if="episode.runtime"> · {{ episode.runtime }} {{ t('titles.minutes') }}</span>
            </p>

            <div v-if="episode.stillPath" class="mt-6 overflow-hidden rounded">
                <img :src="tmdbImage(episode.stillPath, 'w780')!" :alt="episode.name" class="w-full object-cover" />
            </div>

            <p v-if="episode.overview" class="mt-6 font-serif leading-relaxed text-lb-text">{{ episode.overview }}</p>

            <div v-if="viewer" class="mt-8 flex flex-wrap items-center gap-6 rounded bg-lb-surface p-4">
                <button type="button" class="flex items-center gap-2" @click="toggleWatched">
                    <CheckCircle2 v-if="viewer.watched" class="size-7 text-lb-green" />
                    <Circle v-else class="size-7 text-lb-line hover:text-lb-text" />
                    <span class="text-sm text-lb-text">{{ viewer.watched ? t('actions.watched') : t('actions.markWatched') }}</span>
                </button>

                <div class="flex items-center gap-2">
                    <span class="text-xs uppercase tracking-wide text-lb-muted">{{ t('actions.rate') }}</span>
                    <RatingStars :model-value="viewer.rating" size="sm" @update:model-value="rate" />
                </div>

                <button
                    type="button"
                    class="ml-auto rounded bg-lb-green-dark px-4 py-2 text-[0.75rem] font-bold uppercase tracking-[0.075em] text-white hover:bg-lb-green"
                    @click="logOpen = true"
                >
                    {{ t('actions.logReview') }}
                </button>

                <LogModal
                    v-model:open="logOpen"
                    :loggable="{ type: 'episode', id: episode.id, name: `${show.title} ${episode.code}` }"
                    :has-logged="viewer.hasLogged"
                />
            </div>

            <div class="mt-4 rounded bg-lb-panel p-4">
                <div class="section-divider">
                    <h3 class="section-heading">{{ t('titles.ratings') }}</h3>
                </div>
                <div v-if="episode.ratings.count > 0" class="mt-3 flex items-baseline gap-2">
                    <span class="text-2xl font-light text-white">{{ episode.ratings.average }}</span>
                    <span class="text-xs text-lb-muted">({{ episode.ratings.count }})</span>
                </div>
                <p v-else class="mt-3 text-xs text-lb-muted">{{ t('titles.noRatings') }}</p>
            </div>

            <ReviewsSection :reviews="reviews" />
        </div>
    </AppLayout>
</template>
