<script setup lang="ts">
import PosterCard from '@/components/PosterCard.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { tmdbImage } from '@/lib/tmdb';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface Credit {
    tmdbId: number;
    type: 'movie' | 'tv';
    title: string;
    posterPath: string | null;
    year: number | null;
    role: string | null;
}

const props = defineProps<{
    person: {
        id: number;
        tmdbId: number;
        name: string;
        profilePath: string | null;
        biography: string | null;
        birthday: string | null;
        deathday: string | null;
        placeOfBirth: string | null;
        knownFor: string | null;
        credits: Record<string, Credit[]>;
    };
}>();

const { t } = useI18n();

const photo = computed(() => tmdbImage(props.person.profilePath, 'w342'));

// Primero aquello por lo que se la conoce; el resto detrás
const ORDER = ['directing', 'acting', 'producing', 'writing'] as const;

const sections = computed(() => {
    const known = props.person.knownFor?.toLowerCase();
    const preferred = known === 'acting' ? 'acting' : known === 'directing' ? 'directing' : null;

    return ORDER.filter((key) => (props.person.credits[key]?.length ?? 0) > 0)
        .sort((a, b) => (a === preferred ? -1 : b === preferred ? 1 : 0))
        .map((key) => ({ key, credits: props.person.credits[key] }));
});

const totalCredits = computed(() => Object.values(props.person.credits).reduce((sum, list) => sum + list.length, 0));

/** El año de nacimiento/fallecimiento alcanza; la fecha exacta es ruido acá. */
function lifespan(): string | null {
    const born = props.person.birthday?.slice(0, 4);
    const died = props.person.deathday?.slice(0, 4);

    if (!born) return died ? `† ${died}` : null;

    return died ? `${born} – ${died}` : born;
}

function cardFor(credit: Credit) {
    return {
        type: credit.type,
        tmdbId: credit.tmdbId,
        title: credit.title,
        posterPath: credit.posterPath,
        year: credit.year,
        url: route('titles.resolve', { type: credit.type, tmdbId: credit.tmdbId }),
    };
}
</script>

<template>
    <AppLayout>
        <Head :title="person.name" />

        <div class="grid gap-8 md:grid-cols-[230px_1fr]">
            <div>
                <div class="aspect-[2/3] w-full max-w-[230px] overflow-hidden rounded bg-lb-panel shadow-[inset_0_0_0_1px_rgba(221,238,255,0.075)]">
                    <img v-if="photo" :src="photo" :alt="person.name" class="h-full w-full object-cover" />
                </div>
            </div>

            <div class="min-w-0">
                <h1 class="font-serif text-3xl font-bold text-white">{{ person.name }}</h1>

                <p class="mt-1 text-sm text-lb-muted">
                    <span v-if="person.knownFor">{{ t(`person.department.${person.knownFor.toLowerCase()}`, person.knownFor) }}</span>
                    <template v-if="lifespan()"> · {{ lifespan() }}</template>
                    <template v-if="person.placeOfBirth"> · {{ person.placeOfBirth }}</template>
                </p>

                <p v-if="person.biography" class="mt-4 whitespace-pre-line font-serif leading-relaxed text-lb-text">
                    {{ person.biography }}
                </p>

                <p class="mt-4 text-xs text-lb-muted">{{ t('person.creditsCount', { count: totalCredits }) }}</p>
            </div>
        </div>

        <section v-for="section in sections" :key="section.key" class="mt-10">
            <div class="section-divider flex items-baseline justify-between">
                <h2 class="section-heading">{{ t(`person.section.${section.key}`) }}</h2>
                <span class="text-xs text-lb-muted">{{ section.credits.length }}</span>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
                <div v-for="credit in section.credits" :key="`${credit.type}-${credit.tmdbId}-${credit.role}`">
                    <PosterCard :card="cardFor(credit)" show-title />
                    <p v-if="credit.role" class="mt-0.5 truncate text-[11px] text-lb-muted" :title="credit.role">{{ credit.role }}</p>
                </div>
            </div>
        </section>
    </AppLayout>
</template>
