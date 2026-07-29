<script setup lang="ts">
import { computed } from 'vue';

/**
 * Barras verticales de una sola serie (magnitud sobre categorías discretas).
 * Una sola serie → un solo hue y sin leyenda: el título nombra la serie.
 */
export interface Bar {
    label: string;
    value: number;
    /** Etiqueta corta bajo el eje; si falta se usa `label` */
    tick?: string;
    /** Texto extra para el tooltip */
    hint?: string;
}

const props = withDefaults(
    defineProps<{
        bars: Bar[];
        height?: number;
        /** Muestra el valor sobre la barra más alta (etiquetado selectivo) */
        labelMax?: boolean;
        unit?: string;
    }>(),
    { height: 140, labelMax: true, unit: '' },
);

const max = computed(() => Math.max(...props.bars.map((bar) => bar.value), 1));

// Altura mínima visible de 3px para que un valor > 0 nunca se lea como vacío
const bars = computed(() =>
    props.bars.map((bar) => ({
        ...bar,
        percent: bar.value > 0 ? Math.max(3, Math.round((bar.value / max.value) * 100)) : 0,
        isMax: bar.value === max.value && bar.value > 0,
    })),
);
</script>

<template>
    <div>
        <!-- gap-0.5 = 2px de superficie entre barras -->
        <div class="flex items-end gap-0.5" :style="{ height: `${height}px` }">
            <div v-for="(bar, index) in bars" :key="index" class="group relative flex h-full flex-1 flex-col justify-end">
                <span v-if="labelMax && bar.isMax" class="mb-1 text-center text-[10px] font-semibold tabular-nums text-lb-text">
                    {{ bar.value }}
                </span>
                <!-- Extremo superior redondeado 4px, anclado a la línea base -->
                <div
                    class="w-full rounded-t-[4px] bg-lb-green/85 transition-colors group-hover:bg-lb-green"
                    :style="{ height: `${bar.percent}%` }"
                ></div>

                <!-- Tooltip por marca -->
                <div
                    class="pointer-events-none absolute bottom-full left-1/2 z-10 mb-1 hidden -translate-x-1/2 whitespace-nowrap rounded bg-lb-bg px-2 py-1 text-[11px] text-white shadow-lg ring-1 ring-lb-line/50 group-hover:block"
                    role="tooltip"
                >
                    <span class="font-semibold">{{ bar.label }}</span>
                    · {{ bar.value }}{{ unit ? ` ${unit}` : '' }}
                    <template v-if="bar.hint"> · {{ bar.hint }}</template>
                </div>
            </div>
        </div>

        <!-- Eje recesivo -->
        <div class="mt-1 flex gap-0.5 border-t border-lb-line/30 pt-1">
            <span v-for="(bar, index) in bars" :key="index" class="flex-1 truncate text-center text-[10px] tabular-nums text-lb-muted">
                {{ bar.tick ?? bar.label }}
            </span>
        </div>
    </div>
</template>
