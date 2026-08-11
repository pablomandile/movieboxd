<script setup lang="ts">
import { copyToClipboard } from '@/lib/clipboard';
import { Check, Link2, Mail, Share2, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        /** Qué se comparte. Por defecto, la página actual. */
        url?: string;
        title: string;
        /** block: botón ancho (panel de acciones) · inline: chip para barras de acciones */
        variant?: 'block' | 'inline';
    }>(),
    { variant: 'block' },
);

const { t } = useI18n();

const open = ref(false);
const copied = ref(false);
const copyFailed = ref(false);
const linkInput = ref<HTMLInputElement | null>(null);

const shareUrl = computed(() => props.url ?? (typeof window !== 'undefined' ? window.location.href : ''));
const text = computed(() => `${props.title} — ${shareUrl.value}`);

// En celular abre el selector nativo del sistema (todas las apps instaladas)
const hasNativeShare = computed(() => typeof navigator !== 'undefined' && typeof navigator.share === 'function');

const targets = computed(() => [
    { key: 'whatsapp', label: 'WhatsApp', href: `https://wa.me/?text=${encodeURIComponent(text.value)}` },
    {
        key: 'telegram',
        label: 'Telegram',
        href: `https://t.me/share/url?url=${encodeURIComponent(shareUrl.value)}&text=${encodeURIComponent(props.title)}`,
    },
    { key: 'x', label: 'X', href: `https://twitter.com/intent/tweet?text=${encodeURIComponent(text.value)}` },
]);

const mailtoHref = computed(() => `mailto:?subject=${encodeURIComponent(props.title)}&body=${encodeURIComponent(text.value)}`);

async function copy() {
    copyFailed.value = false;

    if (await copyToClipboard(shareUrl.value)) {
        copied.value = true;
        setTimeout(() => (copied.value = false), 2500);

        return;
    }

    // Último recurso: dejar el enlace seleccionado para copiarlo a mano
    linkInput.value?.select();
    copyFailed.value = true;
}

async function nativeShare() {
    try {
        await navigator.share({ title: props.title, url: shareUrl.value });
        open.value = false;
    } catch {
        // El usuario canceló el selector: no es un error que haya que mostrar
    }
}
</script>

<template>
    <button
        v-if="variant === 'inline'"
        type="button"
        class="flex items-center gap-1.5 rounded bg-lb-surface px-3 py-1.5 text-xs text-lb-text transition-colors hover:text-white"
        @click="open = true"
    >
        <Share2 class="size-3.5" />
        {{ t('share.action') }}
    </button>

    <button
        v-else
        type="button"
        class="flex w-full items-center justify-center gap-2 rounded border border-lb-line/60 py-2 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-lb-text transition-colors hover:border-lb-blue hover:text-white"
        @click="open = true"
    >
        <Share2 class="size-4" />
        {{ t('share.action') }}
    </button>

    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="open = false">
            <div class="w-full max-w-sm rounded-lg bg-lb-surface p-6 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h2 class="min-w-0 truncate text-lg font-bold text-white">{{ t('share.title') }}</h2>
                    <button type="button" :aria-label="t('pwa.close')" class="shrink-0 text-lb-muted hover:text-white" @click="open = false">
                        <X class="size-5" />
                    </button>
                </div>

                <!-- Copiar el enlace -->
                <button
                    type="button"
                    class="flex w-full items-center gap-3 rounded border border-lb-line/60 px-3 py-3 text-left transition-colors hover:border-lb-blue"
                    @click="copy"
                >
                    <component :is="copied ? Check : Link2" class="size-5 shrink-0" :class="copied ? 'text-lb-green' : 'text-lb-text'" />
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-semibold text-white">
                            {{ copied ? t('share.copied') : t('share.copyLink') }}
                        </span>
                        <span class="block truncate text-xs text-lb-muted">{{ shareUrl }}</span>
                    </span>
                </button>

                <!-- Solo aparece si falló el copiado, para poder seleccionarlo a mano -->
                <input
                    v-if="copyFailed"
                    ref="linkInput"
                    :value="shareUrl"
                    readonly
                    class="mt-2 w-full rounded border border-lb-line/60 bg-lb-bg px-3 py-2 text-xs text-lb-text"
                    @focus="($event.target as HTMLInputElement).select()"
                />
                <p v-if="copyFailed" class="mt-1 text-xs text-lb-muted">{{ t('share.copyManually') }}</p>

                <!-- Apps -->
                <div class="mt-4 grid grid-cols-4 gap-2">
                    <a
                        v-for="target in targets"
                        :key="target.key"
                        :href="target.href"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="flex flex-col items-center gap-1.5 rounded border border-lb-line/60 py-3 text-lb-text transition-colors hover:border-lb-blue hover:text-white"
                    >
                        <svg v-if="target.key === 'whatsapp'" viewBox="0 0 24 24" class="size-6" fill="currentColor">
                            <path
                                d="M17.47 14.38c-.3-.15-1.75-.86-2.02-.96-.27-.1-.47-.15-.67.15-.2.3-.77.96-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.47-1.75-1.64-2.05-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.6-.92-2.2-.24-.58-.48-.5-.67-.5h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.01-1.04 2.47s1.06 2.86 1.21 3.06c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.7.63.71.23 1.36.19 1.87.12.57-.09 1.75-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38a9.87 9.87 0 0 0 4.79 1.22h.01c5.46 0 9.91-4.45 9.91-9.91 0-2.65-1.03-5.14-2.9-7.01A9.82 9.82 0 0 0 12.04 2zm0 18.15h-.01a8.2 8.2 0 0 1-4.19-1.15l-.3-.18-3.12.82.83-3.04-.2-.31a8.16 8.16 0 0 1-1.25-4.38c0-4.54 3.7-8.24 8.25-8.24 2.2 0 4.27.86 5.83 2.42a8.2 8.2 0 0 1 2.41 5.83c0 4.54-3.7 8.23-8.25 8.23z"
                            />
                        </svg>
                        <svg v-else-if="target.key === 'telegram'" viewBox="0 0 24 24" class="size-6" fill="currentColor">
                            <path
                                d="M11.94 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm4.64 6.85-1.55 7.31c-.12.52-.42.65-.85.4l-2.35-1.73-1.13 1.09c-.13.13-.24.24-.48.24l.17-2.43 4.42-4c.2-.17-.04-.26-.29-.1L9 12.09l-2.32-.73c-.5-.16-.51-.5.1-.75l9.08-3.5c.42-.15.79.1.72.74z"
                            />
                        </svg>
                        <svg v-else viewBox="0 0 24 24" class="size-6" fill="currentColor">
                            <path
                                d="M18.9 2.5h3.32l-7.25 8.29L23.5 21.5h-6.68l-5.23-6.84-5.98 6.84H2.28l7.75-8.86L2 2.5h6.85l4.73 6.25L18.9 2.5zm-1.16 17h1.84L7.34 4.4H5.36l12.38 15.1z"
                            />
                        </svg>
                        <span class="text-[0.6875rem]">{{ target.label }}</span>
                    </a>

                    <a
                        :href="mailtoHref"
                        class="flex flex-col items-center gap-1.5 rounded border border-lb-line/60 py-3 text-lb-text transition-colors hover:border-lb-blue hover:text-white"
                    >
                        <Mail class="size-6" />
                        <span class="text-[0.6875rem]">{{ t('share.email') }}</span>
                    </a>
                </div>

                <!-- Selector nativo: solo existe en móviles y navegadores compatibles -->
                <button
                    v-if="hasNativeShare"
                    type="button"
                    class="mt-3 w-full rounded bg-lb-panel py-2 text-xs font-bold uppercase tracking-[0.075em] text-lb-text transition-colors hover:text-white"
                    @click="nativeShare"
                >
                    {{ t('share.more') }}
                </button>
            </div>
        </div>
    </Teleport>
</template>
