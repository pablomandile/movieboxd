<script setup lang="ts">
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

/**
 * Barras por día, una sola serie: el título la nombra, así que no lleva leyenda.
 * Al pasar el mouse (o tocar) una columna, el encabezado muestra ese día y su valor.
 */
const props = defineProps<{
    title: string;
    points: { date: string; value: number }[];
    locale: string;
    /** sum: total del período. average: promedio diario, para series donde sumar días no tiene sentido (usuarios activos). */
    summary?: 'sum' | 'average';
}>();

const { t } = useI18n();
const hovered = ref<number | null>(null);

const max = computed(() => Math.max(1, ...props.points.map((point) => point.value)));
const total = computed(() => props.points.reduce((sum, point) => sum + point.value, 0));
const headline = computed(() =>
    props.summary === 'average'
        ? t('admin.statsPage.perDay', {
              value: (total.value / Math.max(1, props.points.length)).toLocaleString(props.locale, { maximumFractionDigits: 1 }),
          })
        : formatNumber(total.value),
);

const dayFormatter = computed(() => new Intl.DateTimeFormat(props.locale, { day: 'numeric', month: 'short', timeZone: 'UTC' }));
const formatDay = (date: string) => dayFormatter.value.format(new Date(`${date}T00:00:00Z`));
const formatNumber = (value: number) => value.toLocaleString(props.locale);

const readout = computed(() => {
    if (hovered.value === null) return null;
    const point = props.points[hovered.value];
    return `${formatDay(point.date)} · ${formatNumber(point.value)}`;
});
</script>

<template>
    <figure class="rounded bg-lb-panel p-4" @mouseleave="hovered = null">
        <figcaption class="flex items-baseline justify-between gap-3">
            <span class="text-[11px] uppercase tracking-wide text-lb-text">{{ title }}</span>
            <span class="text-sm tabular-nums text-white">{{ readout ?? headline }}</span>
        </figcaption>

        <div class="relative mt-3">
            <span class="absolute left-0 top-0 text-[10px] tabular-nums leading-none text-lb-muted">{{ formatNumber(max) }}</span>
            <div class="flex h-24 items-end gap-[2px] border-b border-lb-line/40 pt-4" role="img" :aria-label="`${title}: ${headline}`">
                <div
                    v-for="(point, index) in points"
                    :key="point.date"
                    class="flex h-full flex-1 cursor-default items-end"
                    @mouseenter="hovered = index"
                    @click="hovered = index"
                >
                    <div
                        v-if="point.value > 0"
                        class="w-full rounded-t-[4px] transition-colors"
                        :class="hovered === index ? 'bg-lb-bright' : 'bg-lb-blue'"
                        :style="{ height: `${(point.value / max) * 100}%` }"
                    />
                </div>
            </div>
            <div class="mt-1 flex justify-between text-[10px] text-lb-muted">
                <span>{{ formatDay(points[0].date) }}</span>
                <span>{{ formatDay(points[points.length - 1].date) }}</span>
            </div>
        </div>
    </figure>
</template>
