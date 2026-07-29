<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    features: Record<string, boolean>;
    tmdb: { hasKey: boolean; usingEnvFallback: boolean };
    ratingPrior: number;
}>();

const { t } = useI18n();

const form = useForm({
    tmdb_api_key: '',
    clear_tmdb_key: false as boolean,
    rating_prior: props.ratingPrior,
    features: { ...props.features },
});

function submit() {
    form.put(route('admin.settings.update'), {
        preserveScroll: true,
        onSuccess: () => {
            form.tmdb_api_key = '';
            form.clear_tmdb_key = false;
        },
    });
}
</script>

<template>
    <AdminLayout active="settings">
        <Head :title="t('admin.settings')" />

        <form @submit.prevent="submit" class="max-w-xl space-y-10">
            <!-- TMDB -->
            <section>
                <div class="section-divider">
                    <h2 class="section-heading">{{ t('admin.settingsSections.tmdb') }}</h2>
                </div>

                <p class="mt-3 text-xs text-lb-muted">
                    <template v-if="tmdb.hasKey">{{ t('admin.tmdb.keySet') }}</template>
                    <template v-else-if="tmdb.usingEnvFallback">{{ t('admin.tmdb.usingEnv') }}</template>
                    <template v-else>{{ t('admin.tmdb.noKey') }}</template>
                </p>

                <div class="mt-3 grid gap-2">
                    <Label for="tmdb_api_key">{{ t('admin.tmdb.newKey') }}</Label>
                    <Input
                        id="tmdb_api_key"
                        v-model="form.tmdb_api_key"
                        type="password"
                        autocomplete="off"
                        :placeholder="t('admin.tmdb.keyPlaceholder')"
                    />
                    <InputError :message="form.errors.tmdb_api_key" />
                    <p class="text-[11px] text-lb-muted">{{ t('admin.tmdb.encryptedNote') }}</p>
                </div>

                <Label v-if="tmdb.hasKey" class="mt-3 flex items-center gap-3 text-sm text-lb-text">
                    <Checkbox v-model:checked="form.clear_tmdb_key" />
                    {{ t('admin.tmdb.clearKey') }}
                </Label>
            </section>

            <!-- Ratings -->
            <section>
                <div class="section-divider">
                    <h2 class="section-heading">{{ t('admin.settingsSections.ratings') }}</h2>
                </div>
                <div class="mt-3 grid gap-2">
                    <Label for="rating_prior">{{ t('admin.ratingPrior') }}</Label>
                    <Input id="rating_prior" v-model="form.rating_prior" type="number" min="0" max="10000" class="w-32" />
                    <InputError :message="form.errors.rating_prior" />
                    <p class="text-[11px] text-lb-muted">{{ t('admin.ratingPriorHelp') }}</p>
                </div>
            </section>

            <!-- Feature flags -->
            <section>
                <div class="section-divider">
                    <h2 class="section-heading">{{ t('admin.settingsSections.features') }}</h2>
                </div>
                <div class="mt-3 space-y-3">
                    <Label v-for="(_, flag) in form.features" :key="flag" class="flex items-center gap-3 text-sm text-lb-text">
                        <Checkbox v-model:checked="form.features[flag]" />
                        {{ t(`admin.flags.${String(flag).replace('features.', '')}`) }}
                    </Label>
                </div>
            </section>

            <div>
                <Button type="submit" :disabled="form.processing" class="uppercase tracking-[0.075em]">
                    <LoaderCircle v-if="form.processing" class="mr-1 h-4 w-4 animate-spin" />
                    {{ t('log.save') }}
                </Button>
                <span v-if="form.recentlySuccessful" class="ml-3 text-xs text-lb-green">{{ t('admin.saved') }}</span>
            </div>
        </form>
    </AdminLayout>
</template>
