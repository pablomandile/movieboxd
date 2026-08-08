<script setup lang="ts">
import { usePwaInstall } from '@/composables/usePwaInstall';
import { Download, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

// inline: fila del menú mobile; si no, botón de ícono para la barra del header
withDefaults(defineProps<{ inline?: boolean }>(), { inline: false });

const { t } = useI18n();
const { canInstall, isIos, install } = usePwaInstall();

const showHelp = ref(false);
const busy = ref(false);

async function onClick() {
    if (busy.value) return;
    busy.value = true;

    try {
        // 'manual' = iOS, o el prompt ya se consumió: se explican los pasos
        if ((await install()) === 'manual') showHelp.value = true;
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <template v-if="canInstall">
        <button
            v-if="inline"
            type="button"
            :disabled="busy"
            class="flex w-full items-center gap-2 rounded px-3 py-2 text-left text-sm font-semibold uppercase tracking-[0.075em] text-lb-text hover:bg-accent hover:text-white"
            @click="onClick"
        >
            <Download class="size-4 shrink-0" />
            {{ t('pwa.install') }}
        </button>

        <button
            v-else
            type="button"
            :disabled="busy"
            :title="t('pwa.install')"
            :aria-label="t('pwa.install')"
            class="inline-flex h-9 items-center gap-1.5 whitespace-nowrap rounded px-2 text-xs font-bold uppercase tracking-[0.05em] text-lb-text transition-colors hover:text-white sm:text-[0.8125rem]"
            @click="onClick"
        >
            <Download class="size-4 shrink-0" />
            <span class="hidden lg:inline">{{ t('pwa.install') }}</span>
        </button>
    </template>

    <Teleport to="body">
        <div v-if="showHelp" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="showHelp = false">
            <div class="w-full max-w-sm rounded-lg bg-lb-surface p-6 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h2 class="text-lg font-bold text-white">{{ t('pwa.install') }}</h2>
                    <button type="button" :aria-label="t('pwa.close')" class="text-lb-muted hover:text-white" @click="showHelp = false">
                        <X class="size-5" />
                    </button>
                </div>

                <ol v-if="isIos" class="list-decimal space-y-2 pl-5 text-sm text-lb-text">
                    <li>{{ t('pwa.iosStep1') }}</li>
                    <li>{{ t('pwa.iosStep2') }}</li>
                </ol>
                <ol v-else class="list-decimal space-y-2 pl-5 text-sm text-lb-text">
                    <li>{{ t('pwa.menuStep1') }}</li>
                    <li>{{ t('pwa.menuStep2') }}</li>
                </ol>
            </div>
        </div>
    </Teleport>
</template>
