<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

// Aviso en las pantallas de la cuenta cuando corre la demo (APP_DEMO=true).
// Solo se muestra en la demo; cada página oculta su formulario con props.demo.
defineProps<{ body?: string }>();

const { t } = useI18n();
const demo = usePage<SharedData>().props.demo;
</script>

<template>
    <section v-if="demo" class="rounded-lg border border-lb-green/40 bg-lb-green/10 p-4">
        <h2 class="font-semibold">{{ t('demo.accountTitle') }}</h2>
        <p class="mt-1 text-sm text-muted-foreground">{{ body ?? t('demo.accountBody') }}</p>
    </section>
</template>
