<script setup lang="ts">
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import ProgressBar from '@/components/ProgressBar.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import { Head, useForm, usePoll } from '@inertiajs/vue3';
import { LoaderCircle, Upload } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

interface ImportRow {
    id: number;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    total: number;
    processed: number;
    matched: number;
    summary: Record<string, number> | null;
    unmatched: { name: string; year: number | null; reason: string | null }[];
    error: string | null;
    createdAt: string;
    finishedAt: string | null;
}

const props = defineProps<{
    imports: ImportRow[];
    hasActiveImport: boolean;
}>();

const { t } = useI18n();

// Mientras haya una importación activa, refrescar las props cada 2,5 s
const { start, stop } = usePoll(2500, { only: ['imports', 'hasActiveImport'] }, { autoStart: false });

watch(
    () => props.hasActiveImport,
    (isActive) => (isActive ? start() : stop()),
    { immediate: true },
);

const fileInput = ref<HTMLInputElement | null>(null);
const showUnmatchedFor = ref<number | null>(null);

const sectionKeys = ['watched', 'diary', 'watchlist', 'likes', 'lists'] as const;

const form = useForm({
    file: null as File | null,
    sections: {
        watched: true,
        diary: true,
        watchlist: true,
        likes: true,
        lists: true,
    } as Record<string, boolean>,
});

const active = computed(() => props.imports.find((row) => row.status === 'pending' || row.status === 'processing'));
const finished = computed(() => props.imports.filter((row) => row.status === 'completed' || row.status === 'failed'));

const summaryKeys = ['watched', 'ratings', 'diary', 'reviews', 'watchlist', 'likes', 'lists', 'listItems'] as const;

function onFileChange(event: Event) {
    form.file = (event.target as HTMLInputElement).files?.[0] ?? null;
}

function submit() {
    form.post(route('import.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset('file');
            if (fileInput.value) fileInput.value.value = '';
        },
    });
}
</script>

<template>
    <AppLayout>
        <Head :title="t('import.title')" />

        <SettingsLayout>
            <div class="space-y-10">
                <HeadingSmall :title="t('import.title')" :description="t('import.description')" />

                <!-- Importación en curso -->
                <div v-if="active" class="rounded bg-lb-panel p-4">
                    <p class="flex items-center gap-2 text-sm font-semibold text-white">
                        <LoaderCircle class="size-4 animate-spin text-lb-green" />
                        {{ active.status === 'pending' ? t('import.preparing') : t('import.processing') }}
                    </p>
                    <template v-if="active.total > 0">
                        <ProgressBar class="mt-3" :watched="active.processed" :total="active.total" />
                        <p class="mt-2 text-xs tabular-nums text-lb-muted">
                            {{ t('import.progress', { processed: active.processed, total: active.total, matched: active.matched }) }}
                        </p>
                    </template>
                    <p class="mt-2 text-[11px] text-lb-muted">{{ t('import.keepOpen') }}</p>
                </div>

                <!-- Formulario -->
                <form v-else @submit.prevent="submit" class="space-y-5">
                    <div class="grid gap-2">
                        <Label for="file">{{ t('import.file') }}</Label>
                        <input
                            id="file"
                            ref="fileInput"
                            type="file"
                            accept=".zip,application/zip"
                            class="block w-full cursor-pointer rounded bg-lb-surface text-sm text-lb-text file:mr-3 file:cursor-pointer file:rounded-l file:border-0 file:bg-lb-line/40 file:px-3 file:py-2.5 file:text-xs file:font-bold file:uppercase file:tracking-wide file:text-white"
                            @change="onFileChange"
                        />
                        <InputError :message="form.errors.file" />
                        <p class="text-[11px] text-lb-muted">{{ t('import.fileHint') }}</p>
                    </div>

                    <div class="grid gap-2.5">
                        <Label>{{ t('import.sections') }}</Label>
                        <Label v-for="key in sectionKeys" :key="key" class="flex items-center gap-3 text-sm font-normal text-lb-text">
                            <Checkbox v-model:checked="form.sections[key]" />
                            {{ t(`import.section.${key}`) }}
                        </Label>
                        <InputError :message="form.errors.sections" />
                    </div>

                    <Button type="submit" :disabled="form.processing || !form.file" class="uppercase tracking-[0.075em]">
                        <LoaderCircle v-if="form.processing" class="mr-1 h-4 w-4 animate-spin" />
                        <Upload v-else class="mr-1 size-4" />
                        {{ t('import.submit') }}
                    </Button>
                </form>

                <!-- Historial -->
                <div v-if="finished.length" class="space-y-4">
                    <h3 class="section-heading section-divider">{{ t('import.history') }}</h3>

                    <div v-for="row in finished" :key="row.id" class="rounded bg-lb-panel p-4">
                        <p class="text-sm">
                            <span v-if="row.status === 'completed'" class="font-semibold text-lb-green">{{ t('import.completed') }}</span>
                            <span v-else class="font-semibold text-red-400">{{ t('import.failed') }}</span>
                            <span class="ml-2 text-xs text-lb-muted">{{ row.finishedAt ?? row.createdAt }}</span>
                        </p>

                        <p v-if="row.error" class="mt-1 text-xs text-red-400">{{ row.error }}</p>

                        <dl v-if="row.summary" class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-4">
                            <template v-for="key in summaryKeys" :key="key">
                                <div v-if="(row.summary[key] ?? 0) > 0" class="text-xs">
                                    <dt class="inline text-lb-muted">{{ t(`import.summary.${key}`) }}:</dt>
                                    <dd class="inline font-semibold tabular-nums text-white">{{ row.summary[key] }}</dd>
                                </div>
                            </template>
                        </dl>

                        <div v-if="row.unmatched.length" class="mt-3">
                            <button
                                type="button"
                                class="text-xs font-semibold text-lb-orange hover:text-white"
                                @click="showUnmatchedFor = showUnmatchedFor === row.id ? null : row.id"
                            >
                                {{ t('import.unmatched', { count: row.unmatched.length }) }}
                                {{ showUnmatchedFor === row.id ? '▴' : '▾' }}
                            </button>
                            <ul v-if="showUnmatchedFor === row.id" class="mt-2 max-h-56 space-y-0.5 overflow-y-auto text-xs text-lb-muted">
                                <li v-for="(miss, index) in row.unmatched" :key="index">
                                    {{ miss.name }}<span v-if="miss.year"> ({{ miss.year }})</span>
                                    <span v-if="miss.reason" class="text-lb-line"> — {{ miss.reason }}</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
