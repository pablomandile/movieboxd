<script setup lang="ts">
import ListForm from '@/components/ListForm.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import { tmdbImage } from '@/lib/tmdb';
import type { TitleCard } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { GripVertical, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import draggable from 'vuedraggable';

interface ListDetail {
    id: number;
    name: string;
    description: string | null;
    isRanked: boolean;
    isPublic: boolean;
    url: string;
    isOwn: boolean;
    user: { name: string; username: string };
}

interface Item {
    id: number;
    position: number;
    note: string | null;
    card: TitleCard;
}

const props = defineProps<{
    list: ListDetail;
    items: Item[];
}>();

const { t } = useI18n();

const localItems = ref<Item[]>([...props.items]);

watch(
    () => props.items,
    (items) => (localItems.value = [...items]),
);

function persistOrder() {
    router.post(route('lists.reorder', props.list.id), { item_ids: localItems.value.map((item) => item.id) }, { preserveScroll: true });
}

function removeItem(item: Item) {
    if (!confirm(t('lists.confirmRemoveItem'))) return;
    router.delete(route('lists.items.destroy', { list: props.list.id, item: item.id }), { preserveScroll: true });
}

function updateNote(item: Item, event: Event) {
    const note = (event.target as HTMLInputElement).value;
    router.put(route('lists.items.update', { list: props.list.id, item: item.id }), { note }, { preserveScroll: true });
}

function destroyList() {
    if (!confirm(t('lists.confirmDelete'))) return;
    router.delete(route('lists.destroy', props.list.id));
}
</script>

<template>
    <AppLayout>
        <Head :title="`${t('lists.edit')} — ${list.name}`" />

        <div class="mx-auto max-w-3xl">
            <div class="section-divider flex items-baseline justify-between">
                <h1 class="section-heading">{{ t('lists.edit') }}</h1>
                <Link :href="list.url" class="text-xs font-semibold text-lb-blue hover:text-white">{{ t('lists.view') }}</Link>
            </div>

            <!-- Solo el dueño cambia los datos de la lista; el invitado colabora
                 con los títulos (el PUT lo rechaza igual si lo intentara) -->
            <div v-if="list.isOwn" class="mt-6">
                <ListForm
                    :initial="{ name: list.name, description: list.description, isRanked: list.isRanked, isPublic: list.isPublic }"
                    :submit-route="route('lists.update', list.id)"
                    method="put"
                />
            </div>
            <p v-else class="mt-4 rounded bg-lb-surface px-4 py-3 text-sm text-lb-text">
                {{ t('lists.collaboratorNotice', { owner: list.user.name }) }}
            </p>

            <div class="section-divider mt-10">
                <h2 class="section-heading">{{ items.length }} {{ t('lists.titles') }}</h2>
            </div>
            <p class="mt-1 text-xs text-lb-muted">{{ t('lists.dragHint') }}</p>

            <draggable v-model="localItems" item-key="id" handle=".drag-handle" class="mt-3 divide-y divide-lb-line/30" @end="persistOrder">
                <template #item="{ element, index }">
                    <div class="flex items-center gap-3 py-2">
                        <button type="button" class="drag-handle cursor-grab text-lb-muted hover:text-white">
                            <GripVertical class="size-5" />
                        </button>
                        <span v-if="list.isRanked" class="w-6 text-center text-sm font-bold text-lb-green">{{ index + 1 }}</span>
                        <div class="h-[45px] w-[30px] shrink-0 overflow-hidden rounded bg-lb-surface">
                            <img
                                v-if="element.card.posterPath"
                                :src="tmdbImage(element.card.posterPath, 'w92')!"
                                :alt="element.card.title"
                                class="h-full w-full object-cover"
                            />
                        </div>
                        <span class="min-w-0 flex-1 truncate text-sm text-white">
                            {{ element.card.title }}
                            <span v-if="element.card.year" class="text-lb-muted">({{ element.card.year }})</span>
                        </span>
                        <input
                            type="text"
                            :value="element.note ?? ''"
                            :placeholder="t('lists.notePlaceholder')"
                            maxlength="2000"
                            class="w-40 rounded border-0 bg-lb-surface px-2 py-1 text-xs text-white placeholder:text-lb-muted focus:outline-none focus:ring-1 focus:ring-lb-blue/70 sm:w-56"
                            @change="updateNote(element, $event)"
                        />
                        <button type="button" class="p-1 text-lb-muted hover:text-red-400" @click="removeItem(element)">
                            <Trash2 class="size-4" />
                        </button>
                    </div>
                </template>
            </draggable>

            <div class="mt-10 border-t border-lb-line/30 pt-4">
                <Button variant="destructive" class="uppercase tracking-[0.075em]" @click="destroyList">
                    {{ t('lists.delete') }}
                </Button>
            </div>
        </div>
    </AppLayout>
</template>
