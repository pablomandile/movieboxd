<script setup lang="ts">
import DailyBars from '@/components/admin/DailyBars.vue';
import SimplePagination from '@/components/SimplePagination.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import type { Paginated, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

type Series = 'visits' | 'pageviews' | 'logins' | 'actions' | 'activeUsers' | 'signups' | 'tmdbRequests' | 'imports' | 'blocked';

interface StatsUser {
    id: number;
    name: string;
    username: string;
    profileUrl: string;
    createdAt: string;
    lastSeenAt: string | null;
    activeDays: number;
    watched: number;
    diary: number;
    reviews: number;
    lists: number;
    watchlist: number;
    isBanned: boolean;
}

const props = defineProps<{
    demo: boolean;
    summary: { visitsToday: number; visits30: number; logins30: number; actions30: number; active30: number; users: number };
    days: ({ date: string } & Record<Series, number>)[];
    actions: { key: string; total: number }[];
    users: Paginated<StatsUser> | null;
    technical: {
        catalog: { titles: number; people: number; episodes: number };
        queue: { pending: number; failed: number };
        last30: Record<
            | 'tmdbRequests'
            | 'importedTitles'
            | 'importedPeople'
            | 'importedSeasons'
            | 'blockedCrawlers'
            | 'blockedQuota'
            | 'prunedTitles'
            | 'prunedPeople',
            number
        >;
        guestImportsPerDay: number;
        database: { totalBytes: number; tables: { name: string; bytes: number; rows: number }[] } | null;
    };
}>();

const { t } = useI18n();
const page = usePage<SharedData>();
const locale = computed(() => (page.props.locale === 'en' ? 'en' : 'es'));

// En la demo hay una sola cuenta compartida: usuarios activos y altas no dicen nada
const usageSeries = computed<Series[]>(() =>
    props.demo ? ['visits', 'pageviews', 'logins', 'actions'] : ['visits', 'pageviews', 'logins', 'actions', 'activeUsers', 'signups'],
);
const technicalSeries: Series[] = ['tmdbRequests', 'imports', 'blocked'];
const allSeries = computed<Series[]>(() => [...usageSeries.value, ...technicalSeries]);

const points = (series: Series) => props.days.map((day) => ({ date: day.date, value: day[series] }));

const tiles = computed(() => [
    { key: 'visitsToday', value: props.summary.visitsToday },
    { key: 'visits30', value: props.summary.visits30 },
    { key: 'logins30', value: props.summary.logins30 },
    { key: 'actions30', value: props.summary.actions30 },
    ...(props.demo
        ? []
        : [
              { key: 'active30', value: props.summary.active30 },
              { key: 'users', value: props.summary.users },
          ]),
]);

const maxAction = computed(() => Math.max(1, ...props.actions.map((action) => action.total)));

const userColumns = ['activeDays', 'watched', 'diary', 'reviews', 'lists', 'watchlist'] as const;

const number = (value: number) => value.toLocaleString(locale.value);

function bytes(value: number): string {
    const units = ['B', 'KB', 'MB', 'GB'];
    let size = value;
    let unit = 0;
    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }
    return `${size.toLocaleString(locale.value, { maximumFractionDigits: unit >= 2 ? 1 : 0 })} ${units[unit]}`;
}

const relative = computed(() => new Intl.RelativeTimeFormat(locale.value, { numeric: 'auto' }));

function lastSeen(iso: string | null): string {
    if (!iso) return t('admin.statsPage.never');
    const minutes = Math.round((new Date(iso).getTime() - Date.now()) / 60000);
    if (minutes > -60) return relative.value.format(minutes, 'minute');
    if (minutes > -60 * 24) return relative.value.format(Math.round(minutes / 60), 'hour');
    return relative.value.format(Math.round(minutes / 60 / 24), 'day');
}

const dayFormatter = computed(() => new Intl.DateTimeFormat(locale.value, { day: 'numeric', month: 'short', timeZone: 'UTC' }));
const formatDay = (date: string) => dayFormatter.value.format(new Date(`${date}T00:00:00Z`));

const technicalRows = computed(() => {
    const last30 = props.technical.last30;
    return [
        { key: 'tmdbRequests', value: last30.tmdbRequests },
        { key: 'importedTitles', value: last30.importedTitles },
        { key: 'importedPeople', value: last30.importedPeople },
        { key: 'importedSeasons', value: last30.importedSeasons },
        { key: 'blockedCrawlers', value: last30.blockedCrawlers },
        { key: 'blockedQuota', value: last30.blockedQuota },
        { key: 'prunedTitles', value: last30.prunedTitles },
        { key: 'prunedPeople', value: last30.prunedPeople },
    ];
});
</script>

<template>
    <AdminLayout active="stats">
        <Head :title="t('admin.statistics')" />

        <p v-if="demo" class="mb-4 rounded bg-lb-blue/10 p-3 text-sm text-lb-bright ring-1 ring-lb-blue/30">{{ t('admin.statsPage.demoNote') }}</p>

        <div class="grid grid-cols-2 gap-3" :class="demo ? 'sm:grid-cols-4' : 'sm:grid-cols-3 lg:grid-cols-6'">
            <div v-for="tile in tiles" :key="tile.key" class="rounded bg-lb-panel p-4">
                <p class="text-2xl font-bold tabular-nums text-white">{{ number(tile.value) }}</p>
                <p class="mt-0.5 text-[11px] uppercase tracking-wide text-lb-muted">{{ t(`admin.statsPage.summary.${tile.key}`) }}</p>
            </div>
        </div>

        <!-- Uso -->
        <div class="section-divider mt-10">
            <h2 class="section-heading">{{ t('admin.statsPage.usage') }}</h2>
        </div>
        <p class="mt-2 text-xs text-lb-muted">{{ t('admin.statsPage.daysNote') }}</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <DailyBars
                v-for="series in usageSeries"
                :key="series"
                :title="t(`admin.statsPage.series.${series}`)"
                :points="points(series)"
                :locale="locale"
                :summary="series === 'activeUsers' ? 'average' : 'sum'"
            />
        </div>

        <!-- Qué hacen: barras horizontales, cada una con su número -->
        <div class="section-divider mt-10">
            <h2 class="section-heading">{{ t('admin.statsPage.whatTheyDo') }}</h2>
        </div>
        <ul class="mt-4 max-w-2xl space-y-2">
            <li
                v-for="action in actions"
                :key="action.key"
                class="grid grid-cols-[8.5rem_1fr_3rem] items-center gap-3 text-sm sm:grid-cols-[11rem_1fr_3.5rem]"
            >
                <span class="truncate text-lb-text">{{ t(`admin.statsPage.actionNames.${action.key}`) }}</span>
                <span class="h-3 rounded-r-[4px] bg-lb-blue" :style="{ width: `${(action.total / maxAction) * 100}%` }" />
                <span class="text-right tabular-nums text-white">{{ number(action.total) }}</span>
            </li>
        </ul>

        <!-- Usuarios -->
        <template v-if="users">
            <div class="section-divider mt-10">
                <h2 class="section-heading">{{ t('admin.statsPage.perUser') }}</h2>
            </div>

            <table class="mt-3 hidden w-full text-sm md:table">
                <thead>
                    <tr class="border-b border-lb-line/40 text-left text-[11px] uppercase tracking-wide text-lb-muted">
                        <th class="py-2 pr-3">{{ t('admin.table.user') }}</th>
                        <th class="py-2 pr-3">{{ t('admin.statsPage.lastSeen') }}</th>
                        <th v-for="column in userColumns" :key="column" class="py-2 pr-3 text-right">{{ t(`admin.statsPage.columns.${column}`) }}</th>
                        <th class="py-2 text-right">{{ t('admin.table.joined') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-lb-line/20">
                    <tr v-for="user in users.data" :key="user.id">
                        <td class="py-2 pr-3">
                            <Link :href="user.profileUrl" class="font-semibold text-white hover:text-lb-blue">{{ user.name }}</Link>
                            <span class="ml-1 text-xs text-lb-muted">@{{ user.username }}</span>
                            <span v-if="user.isBanned" class="ml-2 rounded-sm bg-red-500/20 px-1.5 text-[10px] uppercase text-red-400">{{
                                t('admin.banned')
                            }}</span>
                        </td>
                        <td class="py-2 pr-3 text-xs text-lb-text">{{ lastSeen(user.lastSeenAt) }}</td>
                        <td v-for="column in userColumns" :key="column" class="py-2 pr-3 text-right tabular-nums text-lb-text">
                            {{ number(user[column]) }}
                        </td>
                        <td class="py-2 text-right text-xs text-lb-muted">{{ user.createdAt }}</td>
                    </tr>
                </tbody>
            </table>

            <ul class="mt-3 space-y-2 md:hidden">
                <li v-for="user in users.data" :key="user.id" class="rounded bg-lb-panel p-3">
                    <div class="flex min-w-0 items-baseline justify-between gap-2">
                        <Link :href="user.profileUrl" class="truncate font-semibold text-white">{{ user.name }}</Link>
                        <span class="shrink-0 text-xs text-lb-text">{{ lastSeen(user.lastSeenAt) }}</span>
                    </div>
                    <p class="truncate text-xs text-lb-muted">@{{ user.username }} · {{ t('admin.table.joined') }} {{ user.createdAt }}</p>
                    <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                        <div v-for="column in userColumns" :key="column" class="flex justify-between gap-1">
                            <dt class="truncate text-lb-muted">{{ t(`admin.statsPage.columns.${column}`) }}</dt>
                            <dd class="tabular-nums text-white">{{ number(user[column]) }}</dd>
                        </div>
                    </dl>
                </li>
            </ul>

            <SimplePagination :prev="users.prev_page_url" :next="users.next_page_url" />
        </template>

        <!-- Técnico -->
        <div class="section-divider mt-10">
            <h2 class="section-heading">{{ t('admin.statsPage.technical') }}</h2>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <DailyBars
                v-for="series in technicalSeries"
                :key="series"
                :title="t(`admin.statsPage.series.${series}`)"
                :points="points(series)"
                :locale="locale"
            />
        </div>

        <div class="mt-3 grid gap-3 lg:grid-cols-3">
            <section class="rounded bg-lb-panel p-4">
                <h3 class="text-[11px] uppercase tracking-wide text-lb-text">{{ t('admin.statsPage.last30') }}</h3>
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div v-for="row in technicalRows" :key="row.key" class="flex justify-between gap-3">
                        <dt class="text-lb-text">{{ t(`admin.statsPage.metrics.${row.key}`) }}</dt>
                        <dd class="tabular-nums text-white">{{ number(row.value) }}</dd>
                    </div>
                </dl>
                <p class="mt-3 text-xs text-lb-muted">{{ t('admin.statsPage.guestQuota', { count: number(technical.guestImportsPerDay) }) }}</p>
            </section>

            <section class="rounded bg-lb-panel p-4">
                <h3 class="text-[11px] uppercase tracking-wide text-lb-text">{{ t('admin.statsPage.catalogAndQueue') }}</h3>
                <dl class="mt-3 space-y-1.5 text-sm">
                    <div v-for="(value, key) in technical.catalog" :key="key" class="flex justify-between gap-3">
                        <dt class="text-lb-text">{{ t(`admin.statsPage.catalog.${key}`) }}</dt>
                        <dd class="tabular-nums text-white">{{ number(value) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3 border-t border-lb-line/30 pt-1.5">
                        <dt class="text-lb-text">{{ t('admin.statsPage.queue.pending') }}</dt>
                        <dd class="tabular-nums text-white">{{ number(technical.queue.pending) }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-lb-text">{{ t('admin.statsPage.queue.failed') }}</dt>
                        <dd class="tabular-nums" :class="technical.queue.failed > 0 ? 'text-lb-orange' : 'text-white'">
                            {{ number(technical.queue.failed) }}
                        </dd>
                    </div>
                </dl>
            </section>

            <section class="rounded bg-lb-panel p-4">
                <h3 class="text-[11px] uppercase tracking-wide text-lb-text">{{ t('admin.statsPage.database') }}</h3>
                <template v-if="technical.database">
                    <p class="mt-3 text-2xl font-bold tabular-nums text-white">{{ bytes(technical.database.totalBytes) }}</p>
                    <p class="text-xs text-lb-muted">{{ t('admin.statsPage.databaseLimit') }}</p>
                    <dl class="mt-3 space-y-1.5 text-sm">
                        <div v-for="table in technical.database.tables" :key="table.name" class="flex justify-between gap-3">
                            <dt class="truncate text-lb-text">{{ table.name }}</dt>
                            <dd class="shrink-0 tabular-nums text-white">{{ bytes(table.bytes) }}</dd>
                        </div>
                    </dl>
                </template>
                <p v-else class="mt-3 text-sm text-lb-muted">{{ t('admin.statsPage.databaseUnavailable') }}</p>
            </section>
        </div>

        <!-- Los mismos datos de los gráficos, en tabla -->
        <details class="mt-10 rounded bg-lb-panel p-4">
            <summary class="cursor-pointer text-sm text-lb-text hover:text-white">{{ t('admin.statsPage.dataTable') }}</summary>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[640px] text-xs">
                    <thead>
                        <tr class="border-b border-lb-line/40 text-left uppercase tracking-wide text-lb-muted">
                            <th class="py-1.5 pr-3">{{ t('admin.statsPage.day') }}</th>
                            <th v-for="series in allSeries" :key="series" class="py-1.5 pr-3 text-right">
                                {{ t(`admin.statsPage.series.${series}`) }}
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-lb-line/20">
                        <tr v-for="day in [...days].reverse()" :key="day.date">
                            <td class="py-1 pr-3 text-lb-text">{{ formatDay(day.date) }}</td>
                            <td v-for="series in allSeries" :key="series" class="py-1 pr-3 text-right tabular-nums text-white">
                                {{ number(day[series]) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </details>
    </AdminLayout>
</template>
