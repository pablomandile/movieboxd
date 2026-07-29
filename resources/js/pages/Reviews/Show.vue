<script setup lang="ts">
import RatingStars from '@/components/RatingStars.vue';
import ReviewCard from '@/components/ReviewCard.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { tmdbImage } from '@/lib/tmdb';
import type { CommentItem, ReviewItem, SharedData } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Trash2 } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    review: ReviewItem;
    subject: { name: string; year: number | null; posterPath: string | null; url: string } | null;
    comments: CommentItem[];
}>();

const { t } = useI18n();
const page = usePage<SharedData>();

const commentForm = useForm({ body: '' });

function submitComment() {
    commentForm.post(route('comments.store', props.review.id), {
        preserveScroll: true,
        onSuccess: () => commentForm.reset(),
    });
}

function deleteComment(comment: CommentItem) {
    if (!confirm(t('reviews.confirmDeleteComment'))) return;
    router.delete(route('comments.destroy', comment.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="`${review.user.name} — ${subject?.name ?? ''}`" />

        <div class="mx-auto grid max-w-3xl gap-8 md:grid-cols-[150px_1fr]">
            <div v-if="subject">
                <Link :href="subject.url" class="block">
                    <div class="aspect-[2/3] overflow-hidden rounded bg-lb-panel shadow-[inset_0_0_0_1px_rgba(221,238,255,0.075)]">
                        <img
                            v-if="subject.posterPath"
                            :src="tmdbImage(subject.posterPath, 'w342')!"
                            :alt="subject.name"
                            class="h-full w-full object-cover"
                        />
                    </div>
                </Link>
            </div>

            <div class="min-w-0">
                <p v-if="subject" class="text-sm">
                    <Link :href="subject.url" class="font-serif text-xl font-bold text-white hover:text-lb-blue">
                        {{ subject.name }}
                    </Link>
                    <span v-if="subject.year" class="ml-1 text-lb-muted">{{ subject.year }}</span>
                </p>

                <div class="mt-2 flex items-center gap-2 text-xs text-lb-muted">
                    <UserAvatar :user="review.user" size="sm" />
                    {{ t('reviews.by') }} <span class="font-semibold text-lb-text">{{ review.user.name }}</span>
                    <RatingStars v-if="review.rating" :model-value="review.rating" readonly size="sm" />
                    <span v-if="review.watchedOn">· {{ review.watchedOn }}</span>
                </div>

                <ReviewCard class="mt-2" :review="review" />

                <!-- Comentarios -->
                <div class="section-divider mt-8">
                    <h2 class="section-heading">{{ t('reviews.comments') }} ({{ comments.length }})</h2>
                </div>

                <ul class="mt-2 divide-y divide-lb-line/30">
                    <li v-for="comment in comments" :key="comment.id" class="flex gap-3 py-3">
                        <UserAvatar :user="comment.user" size="sm" />
                        <div class="min-w-0 flex-1">
                            <p class="text-xs text-lb-muted">
                                <span class="font-semibold text-lb-text">{{ comment.user.name }}</span>
                            </p>
                            <p class="mt-0.5 whitespace-pre-line text-sm text-lb-text">{{ comment.body }}</p>
                        </div>
                        <button v-if="comment.canDelete" type="button" class="p-1 text-lb-muted hover:text-red-400" @click="deleteComment(comment)">
                            <Trash2 class="size-4" />
                        </button>
                    </li>
                </ul>

                <form v-if="page.props.auth.user" @submit.prevent="submitComment" class="mt-4 flex flex-col gap-2">
                    <textarea
                        v-model="commentForm.body"
                        rows="2"
                        :placeholder="t('reviews.commentPlaceholder')"
                        class="w-full rounded border-0 bg-lb-surface p-3 text-sm text-white placeholder:text-lb-muted focus:outline-none focus:ring-2 focus:ring-lb-blue/70"
                        required
                    ></textarea>
                    <div class="flex justify-end">
                        <Button type="submit" :disabled="commentForm.processing" class="uppercase tracking-[0.075em]">
                            {{ t('reviews.comment') }}
                        </Button>
                    </div>
                </form>
                <p v-else class="mt-4 text-sm text-lb-muted">
                    <Link :href="route('login')" class="font-semibold text-lb-blue hover:text-white">{{ t('actions.signInPrompt') }}</Link>
                </p>
            </div>
        </div>
    </AppLayout>
</template>
