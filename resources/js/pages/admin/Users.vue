<script setup lang="ts">
import SimplePagination from '@/components/SimplePagination.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { Paginated, SharedData } from '@/types';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface AdminUser {
    id: number;
    name: string;
    username: string;
    email: string;
    role: 'user' | 'admin';
    isBanned: boolean;
    bannedAt: string | null;
    reviewsCount: number;
    diaryCount: number;
    createdAt: string;
    profileUrl: string;
}

const props = defineProps<{
    search: string;
    users: Paginated<AdminUser>;
}>();

const { t } = useI18n();
const page = usePage<SharedData>();
const query = ref(props.search);

function submitSearch() {
    router.get(route('admin.users.index'), { search: query.value }, { preserveState: true });
}

function changeRole(user: AdminUser, role: 'user' | 'admin') {
    router.put(route('admin.users.role', user.id), { role }, { preserveScroll: true });
}

function toggleBan(user: AdminUser) {
    const message = user.isBanned ? t('admin.confirmUnban', { name: user.name }) : t('admin.confirmBan', { name: user.name });
    if (!confirm(message)) return;

    router.put(route('admin.users.ban', user.id), {}, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout active="users">
        <Head :title="t('admin.users')" />

        <form @submit.prevent="submitSearch" class="relative max-w-sm">
            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-lb-muted" />
            <input
                v-model="query"
                type="search"
                :placeholder="t('admin.searchUsers')"
                class="h-10 w-full rounded border-0 bg-lb-surface pl-10 pr-3 text-sm text-white placeholder:text-lb-muted focus:outline-none focus:ring-2 focus:ring-lb-blue/70"
            />
        </form>

        <div class="mt-6 overflow-x-auto">
            <table class="w-full min-w-[820px] text-sm">
                <thead>
                    <tr class="border-b border-lb-line/40 text-left text-[11px] uppercase tracking-wide text-lb-muted">
                        <th class="py-2 pr-3">{{ t('admin.table.user') }}</th>
                        <th class="py-2 pr-3">{{ t('admin.table.email') }}</th>
                        <th class="py-2 pr-3">{{ t('admin.table.role') }}</th>
                        <th class="py-2 pr-3 text-right">{{ t('admin.table.activity') }}</th>
                        <th class="py-2 pr-3">{{ t('admin.table.joined') }}</th>
                        <th class="py-2 text-right">{{ t('admin.table.actions') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-lb-line/20">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="py-2 pr-3">
                            <Link :href="user.profileUrl" class="font-semibold text-white hover:text-lb-blue">{{ user.name }}</Link>
                            <span class="ml-1 text-xs text-lb-muted">@{{ user.username }}</span>
                            <span v-if="user.isBanned" class="ml-2 rounded-sm bg-red-500/20 px-1.5 text-[10px] uppercase text-red-400">
                                {{ t('admin.banned') }}
                            </span>
                        </td>
                        <td class="py-2 pr-3 text-xs text-lb-text">{{ user.email }}</td>
                        <td class="py-2 pr-3">
                            <select
                                :value="user.role"
                                class="rounded border-0 bg-lb-surface px-2 py-1 text-xs text-white focus:outline-none focus:ring-1 focus:ring-lb-blue/70 disabled:opacity-50"
                                :disabled="user.id === page.props.auth.user?.id"
                                @change="changeRole(user, ($event.target as HTMLSelectElement).value as 'user' | 'admin')"
                            >
                                <option value="user">{{ t('admin.roles.user') }}</option>
                                <option value="admin">{{ t('admin.roles.admin') }}</option>
                            </select>
                        </td>
                        <td class="py-2 pr-3 text-right text-xs text-lb-muted">{{ user.diaryCount }} / {{ user.reviewsCount }}</td>
                        <td class="py-2 pr-3 text-xs text-lb-muted">{{ user.createdAt }}</td>
                        <td class="py-2 text-right">
                            <button
                                type="button"
                                class="rounded px-2 py-1 text-[11px] font-bold uppercase tracking-wide disabled:opacity-40"
                                :class="user.isBanned ? 'bg-lb-surface text-lb-green' : 'bg-lb-surface text-red-400 hover:bg-red-500/20'"
                                :disabled="user.id === page.props.auth.user?.id"
                                @click="toggleBan(user)"
                            >
                                {{ user.isBanned ? t('admin.unban') : t('admin.ban') }}
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <SimplePagination :prev="users.prev_page_url" :next="users.next_page_url" />
    </AdminLayout>
</template>
