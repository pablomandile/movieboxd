<script setup lang="ts">
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';

withDefaults(
    defineProps<{
        user: { name: string; avatar_path: string | null };
        size?: 'sm' | 'md' | 'lg';
    }>(),
    { size: 'md' },
);

const sizes = { sm: 'size-6', md: 'size-8', lg: 'size-10' };
</script>

<template>
    <Avatar class="overflow-hidden rounded-full ring-1 ring-white/25" :class="sizes[size]">
        <AvatarImage v-if="user.avatar_path" :src="user.avatar_path" :alt="user.name" />
        <AvatarFallback class="rounded-full bg-lb-surface text-xs font-semibold text-white">
            {{ getInitials(user.name) }}
        </AvatarFallback>
    </Avatar>
</template>
