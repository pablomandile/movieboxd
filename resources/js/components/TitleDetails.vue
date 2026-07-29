<script setup lang="ts">
import ActionsPanel from '@/components/ActionsPanel.vue';
import RatingHistogram from '@/components/RatingHistogram.vue';
import TitleHero from '@/components/TitleHero.vue';
import { tmdbImage } from '@/lib/tmdb';
import type { TitleDetail, TitleViewer } from '@/types';
import { Clock, Eye, Heart } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    title: TitleDetail;
    viewer?: TitleViewer | null;
}>();

const { t } = useI18n();

const poster = computed(() => tmdbImage(props.title.posterPath, 'w500'));
const people = computed(() => {
    const credits = props.title.credits;
    return props.title.type === 'movie' ? (credits.directors ?? []) : (credits.creators ?? []);
});
const cast = computed(() => (props.title.credits.cast ?? []).slice(0, 12));
</script>

<template>
    <div class="relative">
        <TitleHero :backdrop-path="title.backdropPath" />

        <div class="grid gap-8 md:grid-cols-[230px_1fr_230px]" :class="title.backdropPath ? 'pt-40 sm:pt-64' : 'pt-4'">
            <!-- Póster + contadores de comunidad -->
            <div>
                <div class="aspect-[2/3] w-full max-w-[230px] overflow-hidden rounded bg-lb-panel shadow-[inset_0_0_0_1px_rgba(221,238,255,0.075)]">
                    <img v-if="poster" :src="poster" :alt="title.title" class="h-full w-full object-cover" />
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
                    <span class="font-semibold text-white">{{ people.map((p) => p.name).join(', ') }}</span>
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
                    <div class="mt-3 flex flex-wrap gap-2">
                        <span
                            v-for="member in cast"
                            :key="member.tmdb_id"
                            class="rounded-sm bg-lb-surface px-2 py-1 text-xs text-lb-bright"
                            :title="member.character ?? undefined"
                        >
                            {{ member.name }}
                        </span>
                    </div>
                </template>

                <!-- Contenido específico del tipo (ej: temporadas) -->
                <slot />
            </div>

            <!-- Sidebar -->
            <aside class="space-y-4">
                <ActionsPanel :title="title" :viewer="viewer ?? null" />
                <RatingHistogram :histogram="title.ratings.histogram" :count="title.ratings.count" :average="title.ratings.average" />
                <slot name="sidebar" />
            </aside>
        </div>
    </div>
</template>
