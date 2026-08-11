<script setup lang="ts">
import { tmdbImage } from '@/lib/tmdb';
import { X } from 'lucide-vue-next';
import { computed, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    posterPath: string | null;
    alt: string;
}>();

const { t } = useI18n();

const open = ref(false);

// En la ficha alcanza w500; ampliada se pide la resolución alta
const thumb = computed(() => tmdbImage(props.posterPath, 'w500'));
const full = computed(() => tmdbImage(props.posterPath, 'original'));

function onKeydown(event: KeyboardEvent) {
    if (event.key === 'Escape') open.value = false;
}

// Mientras está abierta: sin scroll de fondo y Escape cierra
watch(open, (isOpen) => {
    document.body.style.overflow = isOpen ? 'hidden' : '';

    if (isOpen) {
        window.addEventListener('keydown', onKeydown);
    } else {
        window.removeEventListener('keydown', onKeydown);
    }
});

onUnmounted(() => {
    document.body.style.overflow = '';
    window.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <button
        v-if="thumb"
        type="button"
        class="block w-full cursor-zoom-in overflow-hidden rounded bg-lb-panel shadow-[inset_0_0_0_1px_rgba(221,238,255,0.075)] transition-shadow hover:shadow-[inset_0_0_0_2px_rgba(64,188,244,0.6)]"
        :title="t('poster.expand')"
        :aria-label="t('poster.expand')"
        @click="open = true"
    >
        <img :src="thumb" :alt="alt" class="aspect-[2/3] h-full w-full object-cover" />
    </button>

    <!-- Sin póster: mismo hueco, sin interacción -->
    <div v-else class="aspect-[2/3] w-full rounded bg-lb-panel shadow-[inset_0_0_0_1px_rgba(221,238,255,0.075)]"></div>

    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
            role="dialog"
            aria-modal="true"
            @click="open = false"
        >
            <button
                type="button"
                class="absolute right-4 top-4 rounded-full bg-black/60 p-2 text-white transition-colors hover:bg-black/80"
                :aria-label="t('pwa.close')"
                @click.stop="open = false"
            >
                <X class="size-6" />
            </button>

            <!-- object-contain: la imagen entra entera, sin recortes -->
            <img :src="full ?? thumb!" :alt="alt" class="max-h-[92vh] max-w-full rounded object-contain shadow-2xl" @click.stop />
        </div>
    </Teleport>
</template>
