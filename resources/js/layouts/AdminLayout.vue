<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

defineProps<{
    active: 'dashboard' | 'users' | 'reports' | 'settings';
}>();

const { t } = useI18n();

const tabs = computed(() => [
    { key: 'dashboard', label: t('admin.dashboard'), href: route('admin.dashboard') },
    { key: 'users', label: t('admin.users'), href: route('admin.users.index') },
    { key: 'reports', label: t('admin.reports'), href: route('admin.reports.index') },
    { key: 'settings', label: t('admin.settings'), href: route('admin.settings.edit') },
]);
</script>

<template>
    <AppLayout>
        <h1 class="section-heading section-divider">{{ t('nav.admin') }}</h1>
        <nav class="mt-4 flex gap-1 overflow-x-auto rounded bg-lb-surface p-1">
            <Link
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.href"
                class="relative whitespace-nowrap rounded px-3 py-2 text-[0.9375rem] text-lb-text transition-colors hover:text-white"
                :class="{ 'text-white after:absolute after:inset-x-3 after:bottom-0 after:h-0.5 after:bg-lb-green': tab.key === active }"
            >
                {{ tab.label }}
            </Link>
        </nav>
        <div class="mt-8">
            <slot />
        </div>
    </AppLayout>
</template>
