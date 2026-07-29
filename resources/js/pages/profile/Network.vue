<script setup lang="ts">
import ProfileHeader, { type ProfileData } from '@/components/ProfileHeader.vue';
import UserAvatar from '@/components/UserAvatar.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

interface Member {
    name: string;
    username: string;
    avatar_path: string | null;
}

defineProps<{
    profile: ProfileData;
    followers: Member[];
    following: Member[];
}>();

const { t } = useI18n();
</script>

<template>
    <AppLayout>
        <Head :title="`${profile.name} — ${t('profile.tabs.network')}`" />
        <ProfileHeader :profile="profile" active-tab="network" />

        <div class="mt-6 grid gap-8 md:grid-cols-2">
            <section>
                <div class="section-divider">
                    <h2 class="section-heading">{{ t('profile.stats.followers') }} ({{ followers.length }})</h2>
                </div>
                <ul class="mt-3 space-y-2">
                    <li v-for="member in followers" :key="member.username">
                        <Link :href="route('profiles.show', member.username)" class="flex items-center gap-3 rounded p-2 hover:bg-lb-surface">
                            <UserAvatar :user="member" size="sm" />
                            <span class="text-sm font-semibold text-white">{{ member.name }}</span>
                            <span class="text-xs text-lb-muted">@{{ member.username }}</span>
                        </Link>
                    </li>
                </ul>
            </section>

            <section>
                <div class="section-divider">
                    <h2 class="section-heading">{{ t('profile.stats.following') }} ({{ following.length }})</h2>
                </div>
                <ul class="mt-3 space-y-2">
                    <li v-for="member in following" :key="member.username">
                        <Link :href="route('profiles.show', member.username)" class="flex items-center gap-3 rounded p-2 hover:bg-lb-surface">
                            <UserAvatar :user="member" size="sm" />
                            <span class="text-sm font-semibold text-white">{{ member.name }}</span>
                            <span class="text-xs text-lb-muted">@{{ member.username }}</span>
                        </Link>
                    </li>
                </ul>
            </section>
        </div>
    </AppLayout>
</template>
