<script setup lang="ts">
defineProps<{
    value: string | number;
    label: string;
    hint?: string;
}>();
</script>

<template>
    <div class="rounded bg-lb-panel p-4">
        <p class="text-2xl font-bold tabular-nums text-white">{{ value }}</p>
        <p class="mt-0.5 text-[11px] uppercase tracking-wide text-lb-muted">{{ label }}</p>
        <p v-if="hint" class="mt-0.5 text-[11px] text-lb-muted">{{ hint }}</p>
    </div>
</template>
