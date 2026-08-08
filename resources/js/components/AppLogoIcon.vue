<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!-- Marca Movieboxd: TV retro con antenas, monocromático (hereda currentColor) -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" :class="className" v-bind="$attrs">
        <path d="M6.5 2.5 12 8.5 M17.5 2.5 12 8.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
        <rect x="3" y="8" width="18" height="13.5" rx="2.5" stroke="currentColor" stroke-width="1.8" />
        <rect x="6" y="11" width="9" height="7.5" rx="1" stroke="currentColor" stroke-width="1.4" />
        <circle cx="18" cy="12.8" r="1" fill="currentColor" />
        <circle cx="18" cy="16.4" r="1" fill="currentColor" />
    </svg>
</template>
