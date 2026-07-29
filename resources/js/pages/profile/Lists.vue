<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import ListCard, { type ListSummary } from '@/components/ListCard.vue';
import ProfileHeader, { type ProfileData } from '@/components/ProfileHeader.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { List, Plus } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    profile: ProfileData;
    lists: Paginated<ListSummary>;
}>();

const { t } = useI18n();
</script>

<template>
    <AppLayout>
        <Head :title="`${profile.name} — ${t('nav.lists')}`" />
        <ProfileHeader :profile="profile" active-tab="lists" />

        <div v-if="profile.isOwn" class="mt-6 flex justify-end">
            <Link
                :href="route('lists.create')"
                class="flex items-center gap-1 rounded bg-lb-green-dark px-3 py-1.5 text-[0.75rem] font-bold uppercase tracking-[0.075em] text-white hover:bg-lb-green"
            >
                <Plus class="size-3.5" />
                {{ t('lists.create') }}
            </Link>
        </div>

        <EmptyState v-if="!lists.data.length" :icon="List" :title="t('lists.none')" />
        <div v-else class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <ListCard v-for="list in lists.data" :key="list.id" :list="list" />
        </div>

        <SimplePagination :prev="lists.prev_page_url" :next="lists.next_page_url" />
    </AppLayout>
</template>
