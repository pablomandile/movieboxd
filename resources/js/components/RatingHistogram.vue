<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    histogram: Record<string, number> | null;
    count: number;
    average: number | null;
}>();

const { t } = useI18n();

const bars = computed(() => {
    const values = Array.from({ length: 10 }, (_, index) => props.histogram?.[String(index + 1)] ?? 0);
    const max = Math.max(...values, 1);

    return values.map((value, index) => ({
        value,
        stars: (index + 1) / 2,
        percent: props.count > 0 ? Math.round((value / props.count) * 100) : 0,
        height: value > 0 ? Math.max(4, Math.round((value / max) * 100)) : 2,
    }));
});
</script>

<template>
    <div class="rounded bg-lb-panel p-4">
        <div class="section-divider flex items-baseline justify-between">
            <h3 class="section-heading">{{ t('titles.ratings') }}</h3>
            <span v-if="count > 0" class="text-[11px] text-lb-muted">{{ count }}</span>
        </div>

        <div v-if="count > 0" class="mt-3">
            <div class="flex items-end gap-2">
                <Star class="mb-0.5 size-3 shrink-0 text-lb-green" fill="currentColor" />
                <div class="flex h-16 flex-1 items-end gap-px">
                    <div
                        v-for="(bar, index) in bars"
                        :key="index"
                        class="flex-1 rounded-t-sm bg-lb-line transition-colors hover:bg-lb-text"
                        :style="{ height: `${bar.height}%` }"
                        :title="`${bar.stars}★ — ${bar.value} (${bar.percent}%)`"
                    ></div>
                </div>
                <div class="mb-0.5 flex shrink-0 items-center">
                    <Star v-for="star in 5" :key="star" class="size-3 text-lb-green" fill="currentColor" />
                </div>
            </div>
            <p class="mt-2 text-right">
                <span class="text-xl font-light text-white">{{ average }}</span>
            </p>
        </div>
        <p v-else class="mt-3 text-xs text-lb-muted">{{ t('titles.noRatings') }}</p>
    </div>
</template>
