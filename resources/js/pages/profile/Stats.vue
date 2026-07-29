<script setup lang="ts">
import BarChart, { type Bar } from '@/components/charts/BarChart.vue';
import RankedBars from '@/components/charts/RankedBars.vue';
import EmptyState from '@/components/EmptyState.vue';
import ProfileHeader, { type ProfileData } from '@/components/ProfileHeader.vue';
import StatTile from '@/components/StatTile.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { tmdbImage } from '@/lib/tmdb';
import { Deferred, Head, Link } from '@inertiajs/vue3';
import { BarChart3 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Stats {
    totals: {
        movies: number;
        shows: number;
        episodes: number;
        diaryEntries: number;
        rewatches: number;
        minutes: number;
        hours: number;
        ratings: number;
        averageRating: number | null;
    };
    perYear: { year: number; total: number }[];
    ratingDistribution: Record<string, number>;
    topGenres: { name: string; total: number }[];
    topPeople: { name: string; total: number }[];
    decades: { decade: number; total: number; averageRating: number | null }[];
    mostRewatched: { title: string; total: number; posterPath: string | null; url: string }[];
}

const props = defineProps<{
    profile: ProfileData;
    stats?: Stats;
}>();

const { t } = useI18n();

const hasActivity = computed(() => (props.stats?.totals.diaryEntries ?? 0) > 0 || (props.stats?.totals.movies ?? 0) > 0);

const yearBars = computed<Bar[]>(
    () => props.stats?.perYear.map((row) => ({ label: String(row.year), value: row.total, tick: String(row.year) })) ?? [],
);

const ratingBars = computed<Bar[]>(() => {
    const distribution = props.stats?.ratingDistribution ?? {};

    return Array.from({ length: 10 }, (_, index) => {
        const value = index + 1;
        const stars = value / 2;

        return {
            label: `${stars}★`,
            value: distribution[String(value)] ?? 0,
            tick: value % 2 === 0 ? String(stars) : '',
        };
    });
});

const decadeBars = computed<Bar[]>(
    () =>
        props.stats?.decades.map((row) => ({
            label: `${row.decade}s`,
            value: row.total,
            tick: `${String(row.decade).slice(2)}s`,
            hint: row.averageRating ? `${t('stats.yourAverage')} ${row.averageRating}★` : undefined,
        })) ?? [],
);
</script>

<template>
    <AppLayout>
        <Head :title="`${profile.name} — ${t('profile.tabs.stats')}`" />
        <ProfileHeader :profile="profile" active-tab="stats" />

        <Deferred data="stats">
            <template #fallback>
                <!-- Skeleton con la misma geometría que el contenido final -->
                <div class="mt-6 animate-pulse space-y-6">
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div v-for="index in 4" :key="index" class="h-[86px] rounded bg-lb-panel"></div>
                    </div>
                    <div class="grid gap-6 md:grid-cols-2">
                        <div class="h-48 rounded bg-lb-panel"></div>
                        <div class="h-48 rounded bg-lb-panel"></div>
                    </div>
                </div>
            </template>

            <div v-if="stats">
                <EmptyState v-if="!hasActivity" :icon="BarChart3" :title="t('stats.empty')" :description="t('stats.emptyHint')" />

                <template v-else>
                    <!-- Totales -->
                    <div class="mt-6 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <StatTile
                            :value="stats.totals.hours"
                            :label="t('stats.hoursWatched')"
                            :hint="t('stats.minutes', { count: stats.totals.minutes })"
                        />
                        <StatTile :value="stats.totals.movies" :label="t('stats.movies')" />
                        <StatTile
                            :value="stats.totals.shows"
                            :label="t('stats.shows')"
                            :hint="t('stats.episodesWatched', { count: stats.totals.episodes })"
                        />
                        <StatTile
                            :value="stats.totals.averageRating ?? '—'"
                            :label="t('stats.averageRating')"
                            :hint="t('stats.ratingsCount', { count: stats.totals.ratings })"
                        />
                    </div>
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <StatTile :value="stats.totals.diaryEntries" :label="t('stats.diaryEntries')" />
                        <StatTile :value="stats.totals.rewatches" :label="t('stats.rewatches')" />
                    </div>

                    <div class="mt-10 grid gap-10 md:grid-cols-2">
                        <!-- Actividad por año -->
                        <section v-if="yearBars.length">
                            <div class="section-divider">
                                <h2 class="section-heading">{{ t('stats.perYear') }}</h2>
                            </div>
                            <div class="mt-4">
                                <BarChart :bars="yearBars" :unit="t('stats.entriesUnit')" />
                            </div>
                        </section>

                        <!-- Distribución de calificaciones -->
                        <section v-if="stats.totals.ratings > 0">
                            <div class="section-divider">
                                <h2 class="section-heading">{{ t('stats.ratingDistribution') }}</h2>
                            </div>
                            <div class="mt-4">
                                <BarChart :bars="ratingBars" :unit="t('stats.ratingsUnit')" />
                            </div>
                        </section>

                        <!-- Décadas -->
                        <section v-if="decadeBars.length">
                            <div class="section-divider">
                                <h2 class="section-heading">{{ t('stats.decades') }}</h2>
                            </div>
                            <div class="mt-4">
                                <BarChart :bars="decadeBars" :unit="t('stats.titlesUnit')" />
                            </div>
                        </section>

                        <!-- Géneros -->
                        <section v-if="stats.topGenres.length">
                            <div class="section-divider">
                                <h2 class="section-heading">{{ t('stats.topGenres') }}</h2>
                            </div>
                            <div class="mt-4">
                                <RankedBars :items="stats.topGenres" />
                            </div>
                        </section>

                        <!-- Directores y creadores -->
                        <section v-if="stats.topPeople.length">
                            <div class="section-divider">
                                <h2 class="section-heading">{{ t('stats.topPeople') }}</h2>
                            </div>
                            <div class="mt-4">
                                <RankedBars :items="stats.topPeople" />
                            </div>
                        </section>

                        <!-- Más revisionados -->
                        <section v-if="stats.mostRewatched.length">
                            <div class="section-divider">
                                <h2 class="section-heading">{{ t('stats.mostRewatched') }}</h2>
                            </div>
                            <ul class="mt-4 space-y-2">
                                <li v-for="item in stats.mostRewatched" :key="item.url" class="flex items-center gap-3">
                                    <Link :href="item.url" class="h-[54px] w-9 shrink-0 overflow-hidden rounded bg-lb-surface">
                                        <img
                                            v-if="item.posterPath"
                                            :src="tmdbImage(item.posterPath, 'w92')!"
                                            :alt="item.title"
                                            loading="lazy"
                                            class="h-full w-full object-cover"
                                        />
                                    </Link>
                                    <Link :href="item.url" class="min-w-0 flex-1 truncate text-sm text-white hover:text-lb-blue">
                                        {{ item.title }}
                                    </Link>
                                    <span class="text-xs font-semibold tabular-nums text-lb-muted"> ×{{ item.total }} </span>
                                </li>
                            </ul>
                        </section>
                    </div>
                </template>
            </div>
        </Deferred>
    </AppLayout>
</template>
