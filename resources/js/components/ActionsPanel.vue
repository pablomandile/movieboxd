<script setup lang="ts">
import LogModal from '@/components/LogModal.vue';
import RatingStars from '@/components/RatingStars.vue';
import ShareDialog from '@/components/ShareDialog.vue';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { TitleDetail, TitleViewer } from '@/types';
import { Link, router } from '@inertiajs/vue3';
import { Check, Clock, Eye, Heart, ListPlus, Star } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    title: TitleDetail;
    viewer: TitleViewer | null;
}>();

const { t } = useI18n();
const logOpen = ref(false);

function toggle(action: 'watched' | 'like' | 'watchlist' | 'favorite') {
    router.post(route(`titles.${action}`, props.title.slug), {}, { preserveScroll: true });
}

function rate(value: number | null) {
    router.put(route('ratings.upsert'), { rateable_type: 'title', rateable_id: props.title.id, value }, { preserveScroll: true });
}

function addToList(listId: number) {
    router.post(route('lists.items.store', listId), { title_id: props.title.id }, { preserveScroll: true });
}
</script>

<template>
    <div class="rounded bg-lb-surface p-4">
        <template v-if="viewer">
            <div class="flex items-start justify-around">
                <button type="button" class="group flex flex-col items-center gap-1" @click="toggle('watched')">
                    <Eye class="size-8 transition-colors" :class="viewer.watched ? 'text-lb-green' : 'text-lb-muted group-hover:text-white'" />
                    <span class="text-[11px] text-lb-muted">{{ viewer.watched ? t('actions.watched') : t('actions.watch') }}</span>
                </button>
                <button type="button" class="group flex flex-col items-center gap-1" @click="toggle('like')">
                    <Heart
                        class="size-8 transition-colors"
                        :class="viewer.liked ? 'text-lb-orange' : 'text-lb-muted group-hover:text-white'"
                        :fill="viewer.liked ? 'currentColor' : 'none'"
                    />
                    <span class="text-[11px] text-lb-muted">{{ t('actions.like') }}</span>
                </button>
                <button type="button" class="group flex flex-col items-center gap-1" @click="toggle('watchlist')">
                    <Clock class="size-8 transition-colors" :class="viewer.inWatchlist ? 'text-lb-blue' : 'text-lb-muted group-hover:text-white'" />
                    <span class="text-[11px] text-lb-muted">{{ t('actions.watchlist') }}</span>
                </button>
            </div>

            <div class="mt-4 border-t border-lb-line/30 pt-3 text-center">
                <p class="mb-1 text-xs uppercase tracking-wide text-lb-muted">{{ t('actions.rate') }}</p>
                <div class="flex justify-center">
                    <RatingStars :model-value="viewer.rating" @update:model-value="rate" />
                </div>
            </div>

            <button
                type="button"
                class="mt-3 flex w-full items-center justify-center gap-1.5 rounded py-1.5 text-[0.75rem] font-bold uppercase tracking-[0.075em]"
                :class="
                    viewer.isFavorite
                        ? 'bg-lb-surface text-lb-green ring-1 ring-lb-green/40'
                        : 'bg-lb-surface text-lb-text ring-1 ring-lb-line/50 hover:text-white'
                "
                @click="toggle('favorite')"
            >
                <Star class="size-3.5" :fill="viewer.isFavorite ? 'currentColor' : 'none'" />
                {{ t('actions.favorite') }}
            </button>

            <button
                type="button"
                class="mt-4 w-full rounded bg-lb-green-dark py-2 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-white hover:bg-lb-green"
                @click="logOpen = true"
            >
                {{ t('actions.logReview') }}
            </button>

            <DropdownMenu>
                <DropdownMenuTrigger
                    class="mt-2 flex w-full items-center justify-center gap-1.5 rounded bg-lb-surface py-1.5 text-[0.75rem] font-bold uppercase tracking-[0.075em] text-lb-text ring-1 ring-lb-line/50 hover:text-white"
                >
                    <ListPlus class="size-3.5" />
                    {{ t('actions.addToList') }}
                </DropdownMenuTrigger>
                <DropdownMenuContent align="center" class="w-52">
                    <DropdownMenuItem
                        v-for="list in viewer.lists"
                        :key="list.id"
                        class="cursor-pointer text-xs"
                        :disabled="list.hasTitle"
                        @click="addToList(list.id)"
                    >
                        <Check v-if="list.hasTitle" class="mr-1.5 size-3.5 text-lb-green" />
                        {{ list.name }}
                    </DropdownMenuItem>
                    <DropdownMenuSeparator v-if="viewer.lists.length" />
                    <DropdownMenuItem :as-child="true">
                        <Link :href="route('lists.create')" class="block w-full cursor-pointer text-xs">
                            {{ t('lists.create') }}
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>

            <LogModal
                v-model:open="logOpen"
                :loggable="{ type: 'title', id: title.id, name: title.title }"
                :has-logged="viewer.hasLogged"
                :can-like="true"
            />
        </template>

        <p v-else class="text-center text-sm text-lb-text">
            <Link :href="route('login')" class="font-semibold text-lb-blue hover:text-white">{{ t('actions.signInPrompt') }}</Link>
        </p>

        <!-- Fuera del bloque autenticado: compartir no necesita sesión -->
        <ShareDialog :title="title.year ? `${title.title} (${title.year})` : title.title" />
    </div>
</template>
