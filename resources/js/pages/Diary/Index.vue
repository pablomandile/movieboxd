<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import RatingStars from '@/components/RatingStars.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { tmdbImage } from '@/lib/tmdb';
import type { DiaryEntryItem, Paginated, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, MessageSquareText, Repeat, Trash2 } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    entries: Paginated<DiaryEntryItem>;
}>();

const { t } = useI18n();
const page = usePage<SharedData>();

const monthFormatter = computed(() => new Intl.DateTimeFormat(page.props.locale === 'es' ? 'es' : 'en', { month: 'long', year: 'numeric' }));

const groups = computed(() => {
    const byMonth = new Map<string, DiaryEntryItem[]>();

    for (const entry of props.entries.data) {
        const key = entry.watchedOn.slice(0, 7);
        if (!byMonth.has(key)) byMonth.set(key, []);
        byMonth.get(key)!.push(entry);
    }

    return Array.from(byMonth.entries()).map(([key, items]) => ({
        label: monthFormatter.value.format(new Date(`${key}-01T00:00:00`)),
        items,
    }));
});

function remove(entry: DiaryEntryItem) {
    if (!confirm(t('diary.confirmDelete'))) return;
    router.delete(route('diary.destroy', entry.id), { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('diary.title')" />

        <h1 class="section-heading section-divider">{{ t('diary.title') }}</h1>

        <EmptyState v-if="!entries.data.length" :icon="CalendarDays" :title="t('diary.empty')" :description="t('diary.emptyHint')">
            <Link
                :href="route('search')"
                class="inline-block rounded bg-lb-green-dark px-4 py-2 text-[0.75rem] font-bold uppercase tracking-[0.075em] text-white hover:bg-lb-green"
            >
                {{ t('search.title') }}
            </Link>
        </EmptyState>

        <div v-for="group in groups" :key="group.label" class="mt-8">
            <h2 class="text-sm font-bold uppercase tracking-[0.075em] text-white">{{ group.label }}</h2>
            <ul class="mt-3 divide-y divide-lb-line/30">
                <li v-for="entry in group.items" :key="entry.id" class="flex items-center gap-4 py-3">
                    <span class="w-8 shrink-0 text-center text-lg font-light text-lb-text">
                        {{ entry.watchedOn.slice(8, 10) }}
                    </span>
                    <Link :href="entry.url" class="h-[54px] w-9 shrink-0 overflow-hidden rounded bg-lb-surface">
                        <img
                            v-if="entry.posterPath"
                            :src="tmdbImage(entry.posterPath, 'w92')!"
                            :alt="entry.name"
                            loading="lazy"
                            class="h-full w-full object-cover"
                        />
                    </Link>
                    <div class="min-w-0 flex-1">
                        <Link :href="entry.url" class="font-semibold text-white hover:text-lb-blue">
                            {{ entry.name }}
                        </Link>
                        <p v-if="entry.context" class="truncate text-xs text-lb-muted">{{ entry.context }}</p>
                        <div class="mt-0.5 flex items-center gap-2">
                            <RatingStars v-if="entry.rating" :model-value="entry.rating" readonly size="sm" />
                            <Repeat v-if="entry.isRewatch" class="size-3.5 text-lb-muted" :title="t('diary.rewatch')" />
                            <Link
                                v-if="entry.reviewUrl"
                                :href="entry.reviewUrl"
                                class="text-lb-muted hover:text-lb-blue"
                                :title="t('diary.hasReview')"
                            >
                                <MessageSquareText class="size-3.5" />
                            </Link>
                            <span v-for="tag in entry.tags" :key="tag" class="rounded-sm bg-lb-surface px-1.5 text-[10px] text-lb-muted">
                                {{ tag }}
                            </span>
                        </div>
                    </div>
                    <button type="button" class="p-2 text-lb-muted hover:text-red-400" @click="remove(entry)">
                        <Trash2 class="size-4" />
                    </button>
                </li>
            </ul>
        </div>

        <div v-if="entries.prev_page_url || entries.next_page_url" class="mt-8 flex justify-center gap-4">
            <Link
                v-if="entries.prev_page_url"
                :href="entries.prev_page_url"
                class="rounded bg-lb-surface px-4 py-2 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-lb-text hover:text-white"
            >
                ← {{ t('search.previous') }}
            </Link>
            <Link
                v-if="entries.next_page_url"
                :href="entries.next_page_url"
                class="rounded bg-lb-surface px-4 py-2 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-lb-text hover:text-white"
            >
                {{ t('search.next') }} →
            </Link>
        </div>
    </AppLayout>
</template>
