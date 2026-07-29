<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    watched: number;
    total: number;
}>();

const percent = computed(() => (props.total > 0 ? Math.min(100, Math.round((props.watched / props.total) * 100)) : 0));
</script>

<template>
    <div class="h-1.5 w-full overflow-hidden rounded-full bg-lb-line/40" role="progressbar" :aria-valuenow="percent">
        <div class="h-full rounded-full bg-lb-green transition-all" :style="{ width: `${percent}%` }"></div>
    </div>
</template>
