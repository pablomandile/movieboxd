<script setup lang="ts">
import type { WatchProviders } from '@/types';
import { ExternalLink } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{ providers: WatchProviders | null }>();

const { t } = useI18n();

// El orden importa: primero lo que ya se puede ver sin pagar de más
const GROUPS = ['flatrate', 'free', 'ads', 'rent', 'buy'] as const;

const groups = computed(() =>
    GROUPS.map((key) => ({ key, providers: props.providers?.offers?.[key] ?? [] })).filter((group) => group.providers.length > 0),
);

const logo = (path: string | null) => (path ? `https://image.tmdb.org/t/p/w92${path}` : null);
</script>

<template>
    <div v-if="groups.length" class="rounded bg-lb-surface p-4">
        <h2 class="text-xs font-bold uppercase tracking-[0.075em] text-lb-muted">
            {{ t('watch.title') }}
        </h2>

        <div v-for="group in groups" :key="group.key" class="mt-3">
            <p class="text-[0.6875rem] uppercase tracking-[0.05em] text-lb-muted">{{ t(`watch.${group.key}`) }}</p>
            <ul class="mt-1.5 flex flex-wrap gap-2">
                <li v-for="provider in group.providers" :key="provider.id">
                    <!-- Enlazar a JustWatch es condición de uso de estos datos -->
                    <a
                        :href="providers?.link ?? undefined"
                        target="_blank"
                        rel="noopener noreferrer"
                        :title="provider.name"
                        class="block overflow-hidden rounded transition-opacity hover:opacity-80"
                    >
                        <img v-if="logo(provider.logo)" :src="logo(provider.logo)!" :alt="provider.name" loading="lazy" class="size-9 rounded" />
                        <span v-else class="flex size-9 items-center justify-center rounded bg-lb-panel text-[0.6rem] text-lb-text">
                            {{ provider.name.slice(0, 3) }}
                        </span>
                    </a>
                </li>
            </ul>
        </div>

        <a
            v-if="providers?.link"
            :href="providers.link"
            target="_blank"
            rel="noopener noreferrer"
            class="mt-3 inline-flex items-center gap-1 text-[0.6875rem] text-lb-muted transition-colors hover:text-lb-blue"
        >
            {{ t('watch.attribution') }}
            <ExternalLink class="size-3" />
        </a>
    </div>
</template>
