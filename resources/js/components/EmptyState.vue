<script setup lang="ts">
import type { LucideIcon } from 'lucide-vue-next';

defineProps<{
    icon?: LucideIcon;
    title: string;
    description?: string;
}>();
</script>

<template>
    <div class="mt-8 rounded bg-lb-panel/60 px-6 py-12 text-center">
        <component :is="icon" v-if="icon" class="mx-auto size-10 text-lb-line" />
        <p class="mt-3 font-semibold text-white">{{ title }}</p>
        <p v-if="description" class="mx-auto mt-1 max-w-md text-sm text-lb-muted">{{ description }}</p>
        <div class="mt-4">
            <slot />
        </div>
    </div>
</template>
