<script setup lang="ts">
import ActionsPanel from '@/components/ActionsPanel.vue';
import PosterLightbox from '@/components/PosterLightbox.vue';
import RatingHistogram from '@/components/RatingHistogram.vue';
import TitleHero from '@/components/TitleHero.vue';
import WatchProviders from '@/components/WatchProviders.vue';
import { tmdbImage } from '@/lib/tmdb';
import type { TitleDetail, TitleViewer } from '@/types';
import { Link } from '@inertiajs/vue3';
import { Clock, Eye, Heart } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    title: TitleDetail;
    viewer?: TitleViewer | null;
}>();

const { t } = useI18n();

const people = computed(() => {
    const credits = props.title.credits;
    return props.title.type === 'movie' ? (credits.directors ?? []) : (credits.creators ?? []);
});
const cast = computed(() => (props.title.credits.cast ?? []).slice(0, 12));
// Los cuatro primeros van destacados con foto; el resto, solo el nombre
const leadCast = computed(() => cast.value.slice(0, 4));
const restCast = computed(() => cast.value.slice(4));
</script>

<template>
    <!-- -mt-8 cancela el padding superior del main: el backdrop arranca pegado
         al header en vez de dejar una franja negra -->
    <div class="relative" :class="title.backdropPath ? '-mt-8' : ''">
        <TitleHero :backdrop-path="title.backdropPath" />

        <!-- relative z-10: por encima del backdrop, que ocupa el mismo espacio.
             El padding deja verlo asomar; en mobile el hero es más bajo. -->
        <div class="relative z-10 grid gap-8 md:grid-cols-[230px_1fr_230px]" :class="title.backdropPath ? 'pt-20 sm:pt-32 md:pt-44' : 'pt-4'">
            <!-- Póster + contadores de comunidad -->
            <div>
                <div class="relative max-w-[230px]">
                    <PosterLightbox :poster-path="title.posterPath" :alt="title.title" />
                    <!-- Vista más de una vez: contador sobre la portada -->
                    <span
                        v-if="(viewer?.watchCount ?? 0) >= 2"
                        class="pointer-events-none absolute left-2 top-2 z-10 inline-flex items-center gap-1 rounded-full bg-black/75 px-2 py-1 text-xs font-bold text-lb-green"
                        :title="t('actions.watchedTimes', { count: viewer!.watchCount })"
                    >
                        <Eye class="size-3.5" />
                        ×{{ viewer!.watchCount }}
                    </span>
                </div>
                <div class="mt-2 flex max-w-[230px] items-center justify-center gap-4 text-xs text-lb-muted">
                    <span class="flex items-center gap-1" :title="t('actions.watched')">
                        <Eye class="size-4 text-lb-green" /> {{ title.counts.watched }}
                    </span>
                    <span class="flex items-center gap-1" :title="t('actions.like')">
                        <Heart class="size-4 text-lb-orange" /> {{ title.counts.likes }}
                    </span>
                    <span class="flex items-center gap-1" :title="t('actions.watchlist')">
                        <Clock class="size-4 text-lb-blue" /> {{ title.counts.watchlist }}
                    </span>
                </div>
            </div>

            <!-- Info principal -->
            <div class="min-w-0">
                <h1 class="font-serif text-3xl font-bold text-white">
                    {{ title.title }}
                    <span v-if="title.year" class="ml-2 align-middle text-lg font-normal text-lb-text">{{ title.year }}</span>
                </h1>

                <p v-if="people.length" class="mt-1 text-sm text-lb-text">
                    {{ title.type === 'movie' ? t('titles.directedBy') : t('titles.createdBy') }}
                    <template v-for="(person, index) in people" :key="person.tmdb_id">
                        <Link :href="route('people.resolve', person.tmdb_id)" class="font-semibold text-white hover:text-lb-blue">
                            {{ person.name }}
                        </Link>
                        <span v-if="index < people.length - 1" class="text-lb-muted">, </span>
                    </template>
                </p>

                <p v-if="title.originalTitle && title.originalTitle !== title.title" class="mt-1 text-xs text-lb-muted">
                    {{ t('titles.originalTitle') }}: {{ title.originalTitle }}
                </p>

                <p v-if="title.tagline" class="mt-6 text-[0.8125rem] font-semibold uppercase tracking-[0.075em] text-lb-muted">
                    {{ title.tagline }}
                </p>

                <p v-if="title.overview" class="mt-3 font-serif leading-relaxed text-lb-text">
                    {{ title.overview }}
                </p>

                <div v-if="title.genres.length" class="mt-6 flex flex-wrap gap-2">
                    <span v-for="genre in title.genres" :key="genre.id" class="rounded-sm bg-lb-surface px-2 py-1 text-xs text-lb-text">
                        {{ genre.name }}
                    </span>
                </div>

                <template v-if="cast.length">
                    <div class="section-divider mt-8">
                        <h2 class="section-heading">{{ t('titles.cast') }}</h2>
                    </div>
                    <!-- Protagonistas: con foto y personaje -->
                    <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <Link v-for="member in leadCast" :key="member.tmdb_id" :href="route('people.resolve', member.tmdb_id)" class="group block">
                            <div class="aspect-[2/3] overflow-hidden rounded bg-lb-panel shadow-[inset_0_0_0_1px_rgba(221,238,255,0.075)]">
                                <img
                                    v-if="member.profile_path"
                                    :src="tmdbImage(member.profile_path, 'w185')!"
                                    :alt="member.name"
                                    loading="lazy"
                                    class="h-full w-full object-cover transition-opacity group-hover:opacity-80"
                                />
                                <div v-else class="flex h-full w-full items-center justify-center p-2 text-center">
                                    <span class="text-xs text-lb-muted">{{ member.name }}</span>
                                </div>
                            </div>
                            <p class="mt-1.5 truncate text-xs font-semibold text-white group-hover:text-lb-blue">{{ member.name }}</p>
                            <p v-if="member.character" class="truncate text-[11px] text-lb-muted" :title="member.character">
                                {{ member.character }}
                            </p>
                        </Link>
                    </div>

                    <!-- El resto, solo el nombre -->
                    <div v-if="restCast.length" class="mt-3 flex flex-wrap gap-2">
                        <Link
                            v-for="member in restCast"
                            :key="member.tmdb_id"
                            :href="route('people.resolve', member.tmdb_id)"
                            class="rounded-sm bg-lb-surface px-2 py-1 text-xs text-lb-bright hover:text-lb-blue"
                            :title="member.character ?? undefined"
                        >
                            {{ member.name }}
                        </Link>
                    </div>
                </template>

                <!-- Contenido específico del tipo (ej: temporadas) -->
                <slot />
            </div>

            <!-- Sidebar -->
            <aside class="space-y-4">
                <ActionsPanel :title="title" :viewer="viewer ?? null" />
                <WatchProviders :providers="title.watchProviders" />
                <RatingHistogram :histogram="title.ratings.histogram" :count="title.ratings.count" :average="title.ratings.average" />
                <slot name="sidebar" />
            </aside>
        </div>
    </div>
</template>
