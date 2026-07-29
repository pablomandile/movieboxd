<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    username: string;
    isFollowing: boolean;
}>();

const { t } = useI18n();

function toggle() {
    router.post(route('follows.toggle', props.username), {}, { preserveScroll: true });
}
</script>

<template>
    <button
        type="button"
        class="rounded px-4 py-1.5 text-[0.75rem] font-bold uppercase tracking-[0.075em] transition-colors"
        :class="isFollowing ? 'bg-lb-surface text-lb-text ring-1 ring-lb-line/50 hover:text-white' : 'bg-lb-green-dark text-white hover:bg-lb-green'"
        @click="toggle"
    >
        {{ isFollowing ? t('profile.following') : t('profile.follow') }}
    </button>
</template>
