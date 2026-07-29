<script setup lang="ts">
import { tmdbImage } from '@/lib/tmdb';
import { computed } from 'vue';

const props = defineProps<{
    backdropPath: string | null;
}>();

const backdrop = computed(() => tmdbImage(props.backdropPath, 'w1280'));
</script>

<template>
    <!-- Backdrop hero que se funde con el fondo, como en Letterboxd -->
    <div v-if="backdrop" class="absolute inset-x-0 top-0 -z-10 h-[490px] overflow-hidden">
        <img :src="backdrop" alt="" class="h-full w-full object-cover object-top opacity-60" />
        <div class="absolute inset-0 bg-gradient-to-t from-lb-bg via-lb-bg/40 to-lb-bg/30"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-lb-bg/60 via-transparent to-lb-bg/60"></div>
    </div>
</template>
