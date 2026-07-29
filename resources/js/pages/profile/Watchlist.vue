<script setup lang="ts">
import EmptyState from '@/components/EmptyState.vue';
import PosterCard from '@/components/PosterCard.vue';
import ProfileHeader, { type ProfileData } from '@/components/ProfileHeader.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Paginated, TitleCard } from '@/types';
import { Head } from '@inertiajs/vue3';
import { Clock } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    profile: ProfileData;
    titles: Paginated<TitleCard>;
}>();

const { t } = useI18n();
</script>

<template>
    <AppLayout>
        <Head :title="`${profile.name} — ${t('profile.tabs.watchlist')}`" />
        <ProfileHeader :profile="profile" active-tab="watchlist" />

        <EmptyState v-if="!titles.data.length" :icon="Clock" :title="t('watchlist.empty')" />
        <div v-else class="mt-6 grid grid-cols-3 gap-3 sm:grid-cols-4 md:grid-cols-6">
            <PosterCard v-for="card in titles.data" :key="`${card.type}-${card.tmdbId}`" :card="card" show-title />
        </div>

        <SimplePagination :prev="titles.prev_page_url" :next="titles.next_page_url" />
    </AppLayout>
</template>
