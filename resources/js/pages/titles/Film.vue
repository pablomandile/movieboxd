<script setup lang="ts">
import ReviewsSection from '@/components/ReviewsSection.vue';
import TitleDetails from '@/components/TitleDetails.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { ReviewItem, TitleDetail, TitleViewer } from '@/types';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
    title: TitleDetail;
    viewer: TitleViewer | null;
    reviews?: ReviewItem[];
}>();

const { t } = useI18n();
</script>

<template>
    <AppLayout>
        <Head :title="title.title" />

        <TitleDetails :title="title" :viewer="viewer">
            <p v-if="title.runtime" class="mt-6 text-xs text-lb-muted">{{ title.runtime }} {{ t('titles.minutes') }}</p>
            <ReviewsSection :reviews="reviews" />
        </TitleDetails>
    </AppLayout>
</template>
