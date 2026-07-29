<script setup lang="ts">
import { tmdbImage } from '@/lib/tmdb';
import { Link } from '@inertiajs/vue3';
import { Heart, ListOrdered, Lock } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

export interface ListSummary {
    id: number;
    name: string;
    description?: string | null;
    isRanked: boolean;
    isPublic?: boolean;
    itemsCount: number;
    likesCount: number;
    user?: { name: string; username: string; avatar_path: string | null };
    posters: string[];
    url: string;
}

defineProps<{
    list: ListSummary;
}>();

const { t } = useI18n();
</script>

<template>
    <Link :href="list.url" class="group block rounded bg-lb-panel p-4 transition-colors hover:bg-lb-surface">
        <!-- Tira de pósters superpuestos, como Letterboxd -->
        <div class="flex h-24 items-center">
            <div
                v-for="(poster, index) in list.posters.slice(0, 5)"
                :key="index"
                class="-ml-6 h-24 w-16 shrink-0 overflow-hidden rounded shadow-lg ring-1 ring-white/10 first:ml-0"
                :style="{ zIndex: 5 - index }"
            >
                <img :src="tmdbImage(poster, 'w154')!" alt="" loading="lazy" class="h-full w-full object-cover" />
            </div>
            <div v-if="!list.posters.length" class="flex h-24 w-full items-center justify-center rounded bg-lb-surface text-xs text-lb-muted">
                {{ t('lists.empty') }}
            </div>
        </div>

        <p class="mt-3 flex items-center gap-1.5 font-bold text-white group-hover:text-lb-blue">
            <ListOrdered v-if="list.isRanked" class="size-4 text-lb-muted" />
            <Lock v-if="list.isPublic === false" class="size-3.5 text-lb-muted" />
            {{ list.name }}
        </p>
        <p v-if="list.user" class="text-xs text-lb-muted">{{ list.user.name }}</p>
        <p class="mt-1 flex items-center gap-3 text-[11px] text-lb-muted">
            <span>{{ list.itemsCount }} {{ t('lists.titles') }}</span>
            <span class="flex items-center gap-1"><Heart class="size-3" /> {{ list.likesCount }}</span>
        </p>
        <p v-if="list.description" class="mt-1 line-clamp-2 text-xs text-lb-text">{{ list.description }}</p>
    </Link>
</template>
