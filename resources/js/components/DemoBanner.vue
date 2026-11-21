<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { FlaskConical } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

// Franja arriba de todas las páginas cuando corre la demo (APP_DEMO=true):
// avisa que no es la versión final y que los datos se borran cada noche.
const { t } = useI18n();
const page = usePage<SharedData>();
</script>

<template>
    <div v-if="page.props.demo" role="note" class="border-b border-lb-orange/40 bg-lb-orange/10 px-4 py-2 text-center text-sm text-lb-bright">
        <FlaskConical class="mr-1.5 inline size-4 -translate-y-px text-lb-orange" aria-hidden="true" />
        <strong class="font-semibold">{{ t('demo.bannerTitle') }}</strong>
        {{ t('demo.bannerBody') }}
    </div>
</template>
