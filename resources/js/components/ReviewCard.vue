<script setup lang="ts">
import RatingStars from '@/components/RatingStars.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { ReviewItem, SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Flag, Heart, MessageCircle, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        review: ReviewItem;
        showActions?: boolean;
    }>(),
    { showActions: true },
);

const { t } = useI18n();
const page = usePage<SharedData>();
const revealed = ref(false);

const reasons = ['spoiler', 'spam', 'abuse', 'other'] as const;

function toggleLike() {
    router.post(route('reviews.like', props.review.id), {}, { preserveScroll: true });
}

function report(reason: string) {
    router.post(route('reports.store'), { reportable_type: 'review', reportable_id: props.review.id, reason }, { preserveScroll: true });
}

function remove() {
    if (!confirm(t('reviews.confirmDelete'))) return;
    router.delete(route('reviews.destroy', props.review.id), { preserveScroll: true });
}
</script>

<template>
    <article class="py-4">
        <div class="flex items-center gap-2">
            <UserAvatar :user="review.user" size="sm" />
            <p class="text-xs text-lb-muted">
                {{ t('reviews.by') }}
                <span class="font-semibold text-lb-text">{{ review.user.name }}</span>
            </p>
            <RatingStars v-if="review.rating" :model-value="review.rating" readonly size="sm" />
        </div>

        <div class="mt-2">
            <div v-if="review.containsSpoilers && !revealed" class="rounded bg-lb-surface p-3 text-sm text-lb-muted">
                {{ t('reviews.spoilerWarning') }}
                <button type="button" class="ml-1 font-semibold text-lb-blue hover:text-white" @click="revealed = true">
                    {{ t('reviews.reveal') }}
                </button>
            </div>
            <p v-else class="line-clamp-6 whitespace-pre-line font-serif leading-relaxed text-lb-text">{{ review.body }}</p>
        </div>

        <div v-if="showActions" class="mt-2 flex items-center gap-4 text-xs text-lb-muted">
            <button v-if="page.props.auth.user" type="button" class="flex items-center gap-1 hover:text-white" @click="toggleLike">
                <Heart
                    class="size-3.5"
                    :class="review.likedByViewer ? 'text-lb-orange' : ''"
                    :fill="review.likedByViewer ? 'currentColor' : 'none'"
                />
                {{ review.likesCount }}
            </button>
            <span v-else class="flex items-center gap-1"> <Heart class="size-3.5" /> {{ review.likesCount }} </span>

            <Link :href="review.url" class="flex items-center gap-1 hover:text-white">
                <MessageCircle class="size-3.5" /> {{ review.commentsCount }}
            </Link>

            <button v-if="review.isOwn" type="button" class="flex items-center gap-1 hover:text-red-400" @click="remove">
                <Trash2 class="size-3.5" />
            </button>

            <DropdownMenu v-else-if="page.props.auth.user">
                <DropdownMenuTrigger class="flex items-center gap-1 hover:text-white">
                    <Flag class="size-3.5" />
                </DropdownMenuTrigger>
                <DropdownMenuContent align="start" class="w-40">
                    <DropdownMenuItem v-for="reason in reasons" :key="reason" class="cursor-pointer text-xs" @click="report(reason)">
                        {{ t(`reviews.reasons.${reason}`) }}
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    </article>
</template>
