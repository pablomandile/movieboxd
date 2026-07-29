<script setup lang="ts">
import { computed } from 'vue';

/**
 * Barras horizontales ordenadas: magnitud por categoría con etiquetas largas
 * (géneros, directores). Una sola serie → un hue, sin leyenda.
 */
export interface RankedItem {
    name: string;
    total: number;
}

const props = defineProps<{
    items: RankedItem[];
}>();

const max = computed(() => Math.max(...props.items.map((item) => item.total), 1));

const rows = computed(() =>
    props.items.map((item) => ({
        ...item,
        percent: Math.max(2, Math.round((item.total / max.value) * 100)),
    })),
);
</script>

<template>
    <ul class="space-y-1.5">
        <li v-for="row in rows" :key="row.name" class="group grid grid-cols-[1fr_auto] items-center gap-2">
            <div class="min-w-0">
                <p class="truncate text-xs text-lb-text" :title="row.name">{{ row.name }}</p>
                <!-- Pista recesiva + extremo derecho redondeado 4px -->
                <div class="mt-0.5 h-1.5 w-full overflow-hidden rounded-full bg-lb-line/25">
                    <div
                        class="h-full rounded-r-[4px] bg-lb-green/85 transition-colors group-hover:bg-lb-green"
                        :style="{ width: `${row.percent}%` }"
                    ></div>
                </div>
            </div>
            <span class="text-xs font-semibold tabular-nums text-lb-muted">{{ row.total }}</span>
        </li>
    </ul>
</template>
