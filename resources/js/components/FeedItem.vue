<script setup lang="ts">
import RatingStars from '@/components/RatingStars.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { tmdbImage } from '@/lib/tmdb';
import { Link } from '@inertiajs/vue3';
import { Repeat } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

export interface FeedItemData {
    kind: 'diary' | 'review';
    id: string;
    when: string;
    watchedOn: string | null;
    user: { name: string; username: string; avatar_path: string | null };
    subject: { name: string; year: number | null; posterPath: string | null; url: string } | null;
    rating: number | null;
    isRewatch: boolean;
    review: { excerpt: string; containsSpoilers: boolean; url: string } | null;
}

defineProps<{
    item: FeedItemData;
}>();

const { t } = useI18n();
</script>

<template>
    <li class="flex gap-3 py-3">
        <Link v-if="item.subject" :href="item.subject.url" class="h-[81px] w-[54px] shrink-0 overflow-hidden rounded bg-lb-surface">
            <img
                v-if="item.subject.posterPath"
                :src="tmdbImage(item.subject.posterPath, 'w154')!"
                :alt="item.subject.name"
                loading="lazy"
                class="h-full w-full object-cover"
            />
        </Link>
        <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-center gap-1.5 text-xs text-lb-muted">
                <UserAvatar :user="item.user" size="sm" />
                <Link :href="route('profiles.show', item.user.username)" class="font-semibold text-lb-text hover:text-white">
                    {{ item.user.name }}
                </Link>
                {{ item.kind === 'diary' ? t('feed.watched') : t('feed.reviewed') }}
                <Link v-if="item.subject" :href="item.subject.url" class="font-semibold text-white hover:text-lb-blue">
                    {{ item.subject.name }}
                </Link>
                <Repeat v-if="item.isRewatch" class="size-3 text-lb-muted" />
            </p>
            <div class="mt-1 flex items-center gap-2">
                <RatingStars v-if="item.rating" :model-value="item.rating" readonly size="sm" />
                <span v-if="item.watchedOn" class="text-[11px] text-lb-muted">{{ item.watchedOn }}</span>
            </div>
            <p v-if="item.review && !item.review.containsSpoilers" class="mt-1 line-clamp-3 font-serif text-sm text-lb-text">
                {{ item.review.excerpt }}
            </p>
            <Link v-if="item.review" :href="item.review.url" class="mt-0.5 inline-block text-xs font-semibold text-lb-blue hover:text-white">
                {{ item.review.containsSpoilers ? t('reviews.spoilerWarning') + ' ' + t('feed.readReview') : t('feed.readReview') }}
            </Link>
        </div>
    </li>
</template>
