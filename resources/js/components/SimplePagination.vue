<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
    prev: string | null;
    next: string | null;
}>();

const { t } = useI18n();
</script>

<template>
    <div v-if="prev || next" class="mt-8 flex justify-center gap-4">
        <Link
            v-if="prev"
            :href="prev"
            class="rounded bg-lb-surface px-4 py-2 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-lb-text hover:text-white"
        >
            ← {{ t('search.previous') }}
        </Link>
        <Link
            v-if="next"
            :href="next"
            class="rounded bg-lb-surface px-4 py-2 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-lb-text hover:text-white"
        >
            {{ t('search.next') }} →
        </Link>
    </div>
</template>
