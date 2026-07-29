<script setup lang="ts">
import LogModal from '@/components/LogModal.vue';
import ProgressBar from '@/components/ProgressBar.vue';
import RatingStars from '@/components/RatingStars.vue';
import ReviewsSection from '@/components/ReviewsSection.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { tmdbImage } from '@/lib/tmdb';
import type { EpisodeSummary, ReviewItem, SeasonSummary, SeasonViewer } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCircle2, Circle } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    show: { slug: string; title: string; year: number | null; posterPath: string | null; backdropPath: string | null };
    season: SeasonSummary;
    episodes: EpisodeSummary[];
    seasonNumbers: number[];
    viewer: SeasonViewer | null;
    reviews?: ReviewItem[];
}>();

const { t } = useI18n();
const logOpen = ref(false);

const watchedIds = computed(() => new Set(props.viewer?.watchedEpisodeIds ?? []));
const watchedCount = computed(() => watchedIds.value.size);
const allWatched = computed(() => props.episodes.length > 0 && watchedCount.value >= props.episodes.length);

function toggleEpisode(episodeId: number) {
    router.post(route('episodes.watch', episodeId), {}, { preserveScroll: true });
}

function toggleSeason() {
    if (allWatched.value) {
        router.delete(route('seasons.unwatchAll', props.season.id), { preserveScroll: true });
    } else {
        router.post(route('seasons.watchAll', props.season.id), {}, { preserveScroll: true });
    }
}

function rate(value: number | null) {
    router.put(route('ratings.upsert'), { rateable_type: 'season', rateable_id: props.season.id, value }, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="`${show.title} – ${season.name}`" />

        <div class="grid gap-8 md:grid-cols-[230px_1fr]">
            <div>
                <div class="aspect-[2/3] w-full max-w-[230px] overflow-hidden rounded bg-lb-panel shadow-[inset_0_0_0_1px_rgba(221,238,255,0.075)]">
                    <img
                        v-if="season.posterPath || show.posterPath"
                        :src="tmdbImage(season.posterPath ?? show.posterPath, 'w500')!"
                        :alt="season.name"
                        class="h-full w-full object-cover"
                    />
                </div>

                <template v-if="viewer">
                    <div class="mt-4 max-w-[230px] rounded bg-lb-surface p-3">
                        <p class="text-center text-xs text-lb-muted">
                            {{ t('progress.episodes', { watched: watchedCount, total: episodes.length }) }}
                        </p>
                        <ProgressBar class="mt-2" :watched="watchedCount" :total="episodes.length" />

                        <button
                            type="button"
                            class="mt-3 w-full rounded py-1.5 text-[0.75rem] font-bold uppercase tracking-[0.075em]"
                            :class="allWatched ? 'bg-lb-line/50 text-lb-text hover:text-white' : 'bg-lb-green-dark text-white hover:bg-lb-green'"
                            @click="toggleSeason"
                        >
                            {{ allWatched ? t('actions.unmarkSeason') : t('actions.markSeasonWatched') }}
                        </button>

                        <div class="mt-3 border-t border-lb-line/30 pt-2 text-center">
                            <p class="mb-1 text-[0.7rem] uppercase tracking-wide text-lb-muted">{{ t('actions.rate') }}</p>
                            <div class="flex justify-center">
                                <RatingStars :model-value="viewer.rating" size="sm" @update:model-value="rate" />
                            </div>
                        </div>

                        <button
                            type="button"
                            class="mt-3 w-full rounded bg-lb-surface py-1.5 text-[0.75rem] font-bold uppercase tracking-[0.075em] text-lb-text ring-1 ring-lb-line/50 hover:text-white"
                            @click="logOpen = true"
                        >
                            {{ t('actions.logReview') }}
                        </button>

                        <LogModal
                            v-model:open="logOpen"
                            :loggable="{ type: 'season', id: season.id, name: `${show.title} – ${season.name}` }"
                            :has-logged="viewer.hasLogged"
                        />
                    </div>
                </template>
            </div>

            <div class="min-w-0">
                <p class="text-sm">
                    <Link :href="route('shows.show', show.slug)" class="font-semibold text-lb-text hover:text-lb-blue">
                        {{ show.title }}
                        <span v-if="show.year" class="font-normal text-lb-muted">({{ show.year }})</span>
                    </Link>
                </p>
                <h1 class="mt-1 font-serif text-3xl font-bold text-white">{{ season.name }}</h1>
                <p v-if="season.airDate" class="mt-1 text-xs text-lb-muted">{{ t('titles.airDate') }}: {{ season.airDate }}</p>

                <p v-if="season.overview" class="mt-4 font-serif leading-relaxed text-lb-text">{{ season.overview }}</p>

                <div class="mt-4 flex flex-wrap gap-1">
                    <Link
                        v-for="number in seasonNumbers"
                        :key="number"
                        :href="route('seasons.show', { title: show.slug, seasonNumber: number })"
                        class="rounded px-2.5 py-1 text-xs font-semibold"
                        :class="number === season.number ? 'bg-lb-green-dark text-white' : 'bg-lb-surface text-lb-text hover:text-white'"
                    >
                        {{ number === 0 ? '★' : `T${number}` }}
                    </Link>
                </div>

                <div class="section-divider mt-8">
                    <h2 class="section-heading">{{ episodes.length }} {{ t('titles.episodes') }}</h2>
                </div>

                <ul class="mt-3 divide-y divide-lb-line/30">
                    <li v-for="episode in episodes" :key="episode.id" class="flex items-center gap-3 py-3">
                        <button v-if="viewer" type="button" class="shrink-0" :title="t('actions.watched')" @click="toggleEpisode(episode.id)">
                            <CheckCircle2 v-if="watchedIds.has(episode.id)" class="size-6 text-lb-green" />
                            <Circle v-else class="size-6 text-lb-line hover:text-lb-text" />
                        </button>

                        <Link
                            :href="route('episodes.show', { title: show.slug, seasonNumber: season.number, episodeNumber: episode.number })"
                            class="group flex min-w-0 flex-1 gap-4"
                        >
                            <div class="h-[68px] w-[120px] shrink-0 overflow-hidden rounded bg-lb-surface">
                                <img
                                    v-if="episode.stillPath"
                                    :src="tmdbImage(episode.stillPath, 'w300')!"
                                    :alt="episode.name"
                                    loading="lazy"
                                    class="h-full w-full object-cover"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="text-xs font-semibold uppercase tracking-wide text-lb-muted">{{ episode.code }}</p>
                                <p class="truncate font-semibold text-white group-hover:text-lb-blue">{{ episode.name }}</p>
                                <p class="mt-0.5 line-clamp-2 text-xs text-lb-text">{{ episode.overview }}</p>
                                <p class="mt-0.5 text-[11px] text-lb-muted">
                                    <span v-if="episode.airDate">{{ episode.airDate }}</span>
                                    <span v-if="episode.runtime"> · {{ episode.runtime }} {{ t('titles.minutes') }}</span>
                                </p>
                            </div>
                        </Link>
                    </li>
                </ul>

                <ReviewsSection :reviews="reviews" />
            </div>
        </div>
    </AppLayout>
</template>
