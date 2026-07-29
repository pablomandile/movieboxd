<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import ProfileHeader, { type ProfileData } from '@/components/ProfileHeader.vue';
import ReviewCard from '@/components/ReviewCard.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { tmdbImage } from '@/lib/tmdb';
import type { Paginated, ReviewItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { PenLine } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

type ReviewWithSubject = ReviewItem & {
    subject: { name: string; year: number | null; posterPath: string | null; url: string } | null;
};

defineProps<{
    profile: ProfileData;
    reviews: Paginated<ReviewWithSubject>;
}>();

const { t } = useI18n();
</script>

<template>
    <AppLayout>
        <Head :title="`${profile.name} — ${t('profile.tabs.reviews')}`" />
        <ProfileHeader :profile="profile" active-tab="reviews" />

        <EmptyState v-if="!reviews.data.length" :icon="PenLine" :title="t('profile.emptyReviews')" />

        <div v-else class="mt-6 divide-y divide-lb-line/30">
            <div v-for="review in reviews.data" :key="review.id" class="flex gap-4 py-4">
                <Link v-if="review.subject" :href="review.subject.url" class="h-[105px] w-[70px] shrink-0 overflow-hidden rounded bg-lb-surface">
                    <img
                        v-if="review.subject.posterPath"
                        :src="tmdbImage(review.subject.posterPath, 'w154')!"
                        :alt="review.subject.name"
                        loading="lazy"
                        class="h-full w-full object-cover"
                    />
                </Link>
                <div class="min-w-0 flex-1">
                    <Link v-if="review.subject" :href="review.subject.url" class="font-serif font-bold text-white hover:text-lb-blue">
                        {{ review.subject.name }}
                        <span v-if="review.subject.year" class="ml-1 font-sans text-xs font-normal text-lb-muted">{{ review.subject.year }}</span>
                    </Link>
                    <ReviewCard :review="review" />
                </div>
            </div>
        </div>

        <SimplePagination :prev="reviews.prev_page_url" :next="reviews.next_page_url" />
    </AppLayout>
</template>
