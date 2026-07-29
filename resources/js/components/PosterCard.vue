<script setup lang="ts">
import { tmdbImage } from '@/lib/tmdb';
import type { TitleCard } from '@/types';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    card: TitleCard;
    showTitle?: boolean;
}>();

const poster = computed(() => tmdbImage(props.card.posterPath, 'w342'));
</script>

<template>
    <div>
        <Link :href="card.url" class="group block" :title="card.title">
            <div
                class="aspect-[2/3] overflow-hidden rounded bg-lb-panel shadow-[inset_0_0_0_1px_rgba(221,238,255,0.075)] transition-shadow group-hover:shadow-[inset_0_0_0_3px_#00E054]"
            >
                <img v-if="poster" :src="poster" :alt="card.title" loading="lazy" class="h-full w-full object-cover" />
                <div v-else class="flex h-full w-full items-center justify-center p-3 text-center">
                    <span class="text-xs text-lb-muted">{{ card.title }}</span>
                </div>
            </div>
        </Link>
        <p v-if="showTitle" class="mt-1.5 truncate text-xs text-lb-text">
            {{ card.title }}
            <span v-if="card.year" class="text-lb-muted">({{ card.year }})</span>
        </p>
    </div>
</template>
