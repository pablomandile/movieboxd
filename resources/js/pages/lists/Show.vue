<script setup lang="ts">
import PosterCard from '@/components/PosterCard.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { CommentItem, SharedData, TitleCard } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import { Heart, Pencil, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

interface ListDetail {
    id: number;
    name: string;
    description: string | null;
    isRanked: boolean;
    isPublic: boolean;
    likesCount: number;
    commentsCount: number;
    user: { name: string; username: string; avatar_path: string | null };
    isOwn: boolean;
    likedByViewer: boolean;
    url: string;
    editUrl: string;
}

interface Item {
    id: number;
    position: number;
    note: string | null;
    watched: boolean;
    card: TitleCard;
}

const props = defineProps<{
    list: ListDetail;
    items: Item[];
    comments: CommentItem[];
}>();

const { t } = useI18n();
const page = usePage<SharedData>();

const watchedCount = computed(() => props.items.filter((item) => item.watched).length);

const commentForm = useForm({ body: '' });

function toggleLike() {
    router.post(route('lists.like', props.list.id), {}, { preserveScroll: true });
}

function submitComment() {
    commentForm.post(route('lists.comments.store', props.list.id), {
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
        <Head :title="list.name" />

        <div class="mx-auto max-w-4xl">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <p class="flex items-center gap-2 text-xs text-lb-muted">
                        <UserAvatar :user="list.user" size="sm" />
                        <Link :href="route('profiles.show', list.user.username)" class="font-semibold text-lb-text hover:text-white">
                            {{ list.user.name }}
                        </Link>
                    </p>
                    <h1 class="mt-2 font-serif text-3xl font-bold text-white">{{ list.name }}</h1>
                    <p v-if="list.description" class="mt-2 max-w-2xl text-sm text-lb-text">{{ list.description }}</p>
                    <p class="mt-2 text-xs text-lb-muted">
                        {{ items.length }} {{ t('lists.titles') }}
                        <template v-if="page.props.auth.user"> · {{ t('lists.watchedOf', { watched: watchedCount, total: items.length }) }}</template>
                    </p>
                </div>

                <div class="flex items-center gap-2">
                    <button
                        v-if="page.props.auth.user"
                        type="button"
                        class="flex items-center gap-1.5 rounded bg-lb-surface px-3 py-1.5 text-xs text-lb-text hover:text-white"
                        @click="toggleLike"
                    >
                        <Heart
                            class="size-4"
                            :class="list.likedByViewer ? 'text-lb-orange' : ''"
                            :fill="list.likedByViewer ? 'currentColor' : 'none'"
                        />
                        {{ list.likesCount }}
                    </button>
                    <Link
                        v-if="list.isOwn"
                        :href="list.editUrl"
                        class="flex items-center gap-1.5 rounded bg-lb-surface px-3 py-1.5 text-xs text-lb-text hover:text-white"
                    >
                        <Pencil class="size-3.5" />
                        {{ t('lists.edit') }}
                    </Link>
                </div>
            </div>

            <div class="mt-8 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-5">
                <div v-for="item in items" :key="item.id">
                    <div class="relative">
                        <span
                            v-if="list.isRanked"
                            class="absolute -left-1 -top-1 z-10 flex size-6 items-center justify-center rounded-full bg-lb-green-dark text-xs font-bold text-white"
                        >
                            {{ item.position }}
                        </span>
                        <PosterCard :card="item.card" show-title />
                    </div>
                    <p v-if="item.note" class="mt-1 text-[11px] italic text-lb-muted">{{ item.note }}</p>
                </div>
            </div>
            <p v-if="!items.length" class="mt-6 text-sm text-lb-muted">{{ t('lists.empty') }}</p>

            <!-- Comentarios -->
            <div class="section-divider mt-10">
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
        </div>
    </AppLayout>
</template>
