<script setup lang="ts">
import ListForm from '@/components/ListForm.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
</script>

<template>
    <AppLayout>
        <Head :title="t('lists.create')" />

        <h1 class="section-heading section-divider">{{ t('lists.create') }}</h1>
        <div class="mt-6">
            <ListForm :submit-route="route('lists.store')" method="post" />
        </div>
    </AppLayout>
</template>
