<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage<SharedData>();

// Sin credenciales cargadas en el servidor el botón no se muestra
const enabled = computed(() => page.props.oauth?.google === true);
</script>

<template>
    <div v-if="enabled" class="flex flex-col gap-6">
        <!-- Navegación normal, no Inertia: el flujo OAuth sale del sitio -->
        <a
            :href="route('oauth.google.redirect')"
            class="inline-flex h-10 w-full items-center justify-center gap-3 rounded-md border border-lb-line/60 bg-white px-4 text-sm font-semibold text-[#1f1f1f] transition-colors hover:bg-white/90"
        >
            <svg class="size-5" viewBox="0 0 24 24" aria-hidden="true">
                <path
                    fill="#4285F4"
                    d="M23.06 12.25c0-.85-.08-1.67-.22-2.45H12v4.63h6.2a5.3 5.3 0 0 1-2.3 3.48v2.89h3.72c2.18-2 3.44-4.96 3.44-8.55Z"
                />
                <path
                    fill="#34A853"
                    d="M12 24c3.11 0 5.72-1.03 7.62-2.8l-3.72-2.88c-1.03.69-2.35 1.1-3.9 1.1-3 0-5.55-2.03-6.46-4.76H1.69v2.98A11.5 11.5 0 0 0 12 24Z"
                />
                <path fill="#FBBC05" d="M5.54 14.66a6.9 6.9 0 0 1 0-4.41V7.27H1.69a11.5 11.5 0 0 0 0 10.37l3.85-2.98Z" />
                <path
                    fill="#EA4335"
                    d="M12 4.75c1.69 0 3.21.58 4.4 1.72l3.3-3.3C17.71 1.24 15.1 0 12 0 7.52 0 3.65 2.57 1.69 6.31l3.85 2.98C6.45 6.78 9 4.75 12 4.75Z"
                />
            </svg>
            {{ t('auth.continueWithGoogle') }}
        </a>

        <div class="relative text-center">
            <span class="absolute inset-x-0 top-1/2 h-px bg-lb-line/40"></span>
            <span class="relative bg-background px-3 text-xs uppercase tracking-wider text-muted-foreground">
                {{ t('auth.or') }}
            </span>
        </div>
    </div>
</template>
