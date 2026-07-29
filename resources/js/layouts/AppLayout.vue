<script setup lang="ts">
import SiteFooter from '@/components/SiteFooter.vue';
import SiteHeader from '@/components/SiteHeader.vue';
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <div class="flex min-h-screen flex-col bg-lb-bg">
        <SiteHeader />
        <main class="mx-auto w-full max-w-[1120px] flex-1 px-4 py-8">
            <slot />
        </main>
        <SiteFooter />
    </div>
</template>
