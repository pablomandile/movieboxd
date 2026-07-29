<script setup lang="ts">
import { Star } from 'lucide-vue-next';
import { computed, ref } from 'vue';

// Valores 1-10 = 0.5 a 5 estrellas en medios pasos, como Letterboxd
const props = withDefaults(
    defineProps<{
        modelValue?: number | null;
        readonly?: boolean;
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { modelValue: null, readonly: false, size: 'md' },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void;
}>();

const hoverValue = ref<number | null>(null);

const display = computed(() => hoverValue.value ?? props.modelValue ?? 0);

const sizeClass = computed(() => ({ sm: 'size-4', md: 'size-6', lg: 'size-7' })[props.size]);

function onMove(star: number, event: MouseEvent) {
    if (props.readonly) return;
    const target = event.currentTarget as HTMLElement;
    const half = event.offsetX < target.clientWidth / 2;
    hoverValue.value = half ? star * 2 - 1 : star * 2;
}

function onLeave() {
    hoverValue.value = null;
}

function onClick() {
    if (props.readonly || hoverValue.value === null) return;
    // Clic sobre el valor actual → limpiar rating
    emit('update:modelValue', hoverValue.value === props.modelValue ? null : hoverValue.value);
}

function fillFor(star: number): 'full' | 'half' | 'none' {
    if (display.value >= star * 2) return 'full';
    if (display.value === star * 2 - 1) return 'half';
    return 'none';
}
</script>

<template>
    <div class="flex items-center" @mouseleave="onLeave" role="radiogroup">
        <button
            v-for="star in 5"
            :key="star"
            type="button"
            class="relative p-0.5"
            :class="readonly ? 'cursor-default' : 'cursor-pointer'"
            :disabled="readonly"
            @mousemove="onMove(star, $event)"
            @click="onClick"
        >
            <Star :class="sizeClass" class="text-lb-line" fill="currentColor" />
            <span
                v-if="fillFor(star) !== 'none'"
                class="absolute inset-0 overflow-hidden p-0.5"
                :style="{ width: fillFor(star) === 'half' ? '50%' : '100%' }"
            >
                <Star :class="sizeClass" class="text-lb-green" fill="currentColor" />
            </span>
        </button>
    </div>
</template>
