<script setup lang="ts">
import ReviewCard from '@/components/ReviewCard.vue';
import type { ReviewItem } from '@/types';
import { Deferred } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

defineProps<{
    reviews?: ReviewItem[];
}>();

const { t } = useI18n();
</script>

<template>
    <section class="mt-8">
        <div class="section-divider">
            <h2 class="section-heading">{{ t('reviews.popular') }}</h2>
        </div>

        <Deferred data="reviews">
            <template #fallback>
                <!-- Skeleton con la geometría de una ReviewCard -->
                <div class="mt-3 animate-pulse space-y-6">
                    <div v-for="index in 2" :key="index">
                        <div class="flex items-center gap-2">
                            <div class="size-6 rounded-full bg-lb-surface"></div>
                            <div class="h-3 w-32 rounded bg-lb-surface"></div>
                        </div>
                        <div class="mt-2 space-y-1.5">
                            <div class="h-3 w-full rounded bg-lb-surface"></div>
                            <div class="h-3 w-4/5 rounded bg-lb-surface"></div>
                        </div>
                    </div>
                </div>
            </template>

            <div v-if="reviews?.length" class="divide-y divide-lb-line/30">
                <ReviewCard v-for="review in reviews" :key="review.id" :review="review" />
            </div>
            <p v-else class="mt-3 text-xs text-lb-muted">{{ t('reviews.empty') }}</p>
        </Deferred>
    </section>
</template>
