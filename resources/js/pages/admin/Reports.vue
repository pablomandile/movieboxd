<script setup lang="ts">
import SimplePagination from '@/components/SimplePagination.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

interface AdminReport {
    id: number;
    reason: string;
    details: string | null;
    status: 'pending' | 'resolved' | 'dismissed';
    createdAt: string;
    reporter: { name: string; username: string } | null;
    resolvedBy: string | null;
    target: {
        type: string;
        excerpt: string | null;
        containsSpoilers: boolean;
        author: { name: string; username: string };
        url: string | null;
        subject: string | null;
    } | null;
}

defineProps<{
    status: string;
    reports: Paginated<AdminReport>;
}>();

const { t } = useI18n();

const filters = ['pending', 'resolved', 'dismissed', 'all'] as const;

function filterBy(status: string) {
    router.get(route('admin.reports.index'), { status }, { preserveState: true });
}

function resolve(report: AdminReport, action: 'dismiss' | 'delete_content') {
    const message = action === 'delete_content' ? t('admin.confirmDeleteContent') : t('admin.confirmDismiss');
    if (!confirm(message)) return;

    router.put(route('admin.reports.update', report.id), { action }, { preserveScroll: true });
}
</script>

<template>
    <AdminLayout active="reports">
        <Head :title="t('admin.reports')" />

        <div class="flex flex-wrap gap-1">
            <button
                v-for="filter in filters"
                :key="filter"
                type="button"
                class="rounded px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide"
                :class="status === filter ? 'bg-lb-green-dark text-white' : 'bg-lb-surface text-lb-text hover:text-white'"
                @click="filterBy(filter)"
            >
                {{ t(`admin.reportStatus.${filter}`) }}
            </button>
        </div>

        <p v-if="!reports.data.length" class="mt-8 text-sm text-lb-muted">{{ t('admin.noReports') }}</p>

        <ul v-else class="mt-6 space-y-4">
            <li v-for="report in reports.data" :key="report.id" class="rounded bg-lb-panel p-4">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 text-xs text-lb-muted">
                            <span class="rounded-sm bg-lb-surface px-1.5 py-0.5 font-bold uppercase text-lb-orange">
                                {{ t(`reviews.reasons.${report.reason}`) }}
                            </span>
                            <span v-if="report.target" class="rounded-sm bg-lb-surface px-1.5 py-0.5 uppercase">
                                {{ t(`admin.targetTypes.${report.target.type}`) }}
                            </span>
                            <span>{{ report.createdAt }}</span>
                            <span v-if="report.reporter">· {{ t('admin.reportedBy') }} {{ report.reporter.name }}</span>
                        </p>

                        <template v-if="report.target">
                            <p class="mt-2 text-xs text-lb-muted">
                                {{ t('admin.contentBy') }}
                                <Link
                                    :href="route('profiles.show', report.target.author.username)"
                                    class="font-semibold text-lb-text hover:text-white"
                                >
                                    {{ report.target.author.name }}
                                </Link>
                                <span v-if="report.target.subject"> · {{ report.target.subject }}</span>
                            </p>
                            <p class="mt-1 max-w-2xl whitespace-pre-line font-serif text-sm text-lb-text">
                                {{ report.target.excerpt }}
                            </p>
                            <Link
                                v-if="report.target.url"
                                :href="report.target.url"
                                class="mt-1 inline-block text-xs font-semibold text-lb-blue hover:text-white"
                            >
                                {{ t('admin.viewContent') }}
                            </Link>
                        </template>
                        <p v-else class="mt-2 text-xs italic text-lb-muted">{{ t('admin.contentDeleted') }}</p>

                        <p v-if="report.details" class="mt-2 text-xs italic text-lb-muted">“{{ report.details }}”</p>
                    </div>

                    <div v-if="report.status === 'pending'" class="flex shrink-0 gap-2">
                        <button
                            type="button"
                            class="rounded bg-lb-surface px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-lb-text hover:text-white"
                            @click="resolve(report, 'dismiss')"
                        >
                            {{ t('admin.dismiss') }}
                        </button>
                        <button
                            v-if="report.target"
                            type="button"
                            class="rounded bg-lb-surface px-3 py-1.5 text-[11px] font-bold uppercase tracking-wide text-red-400 hover:bg-red-500/20"
                            @click="resolve(report, 'delete_content')"
                        >
                            {{ t('admin.deleteContent') }}
                        </button>
                    </div>
                    <p v-else class="shrink-0 text-[11px] uppercase tracking-wide text-lb-muted">
                        {{ t(`admin.reportStatus.${report.status}`) }}
                        <span v-if="report.resolvedBy"> · {{ report.resolvedBy }}</span>
                    </p>
                </div>
            </li>
        </ul>

        <SimplePagination :prev="reports.prev_page_url" :next="reports.next_page_url" />
    </AdminLayout>
</template>
