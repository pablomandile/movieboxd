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
    <!-- z-0, no -z-10: con z-index negativo la imagen queda detrás del fondo
         opaco del layout (bg-lb-bg) y no se ve nunca -->
    <div v-if="backdrop" class="absolute inset-x-0 top-0 z-0 h-[220px] overflow-hidden sm:h-[320px] md:h-[420px]">
        <img :src="backdrop" alt="" class="h-full w-full object-cover object-top opacity-60" />
        <div class="absolute inset-0 bg-gradient-to-t from-lb-bg via-lb-bg/40 to-lb-bg/30"></div>
        <div class="absolute inset-0 bg-gradient-to-r from-lb-bg/60 via-transparent to-lb-bg/60"></div>
    </div>
</template>
