<script setup lang="ts">
import DemoNotice from '@/components/DemoNotice.vue';
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { SharedData } from '@/types';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { ref } from 'vue';
import { useI18n } from 'vue-i18n';

interface TokenRow {
    id: number;
    name: string;
    lastUsedAt: string | null;
    createdAt: string;
}

defineProps<{
    tokens: TokenRow[];
    newToken: string | null;
    apiUrl: string;
}>();

const { t, locale } = useI18n();
const demo = usePage<SharedData>().props.demo;

const form = useForm({ name: '' });
const create = () => form.post(route('api-tokens.store'), { preserveScroll: true, onSuccess: () => form.reset() });

const revoke = (token: TokenRow) => {
    if (!confirm(t('apiTokens.revokeConfirm', { name: token.name }))) return;
    router.delete(route('api-tokens.destroy', token.id), { preserveScroll: true });
};

const copied = ref(false);
const copy = async (value: string) => {
    await navigator.clipboard.writeText(value);
    copied.value = true;
    setTimeout(() => (copied.value = false), 2000);
};

const fecha = (iso: string) => new Date(iso).toLocaleDateString(locale.value, { day: 'numeric', month: 'short', year: 'numeric' });
</script>

<template>
    <AppLayout>
        <Head :title="t('apiTokens.title')" />

        <SettingsLayout>
            <div class="space-y-8">
                <HeadingSmall :title="t('apiTokens.title')" :description="t('apiTokens.description')" />

                <DemoNotice v-if="demo" :body="t('apiTokens.demoBody')" />

                <template v-else>
                    <!-- Recién creado: se muestra una sola vez -->
                    <div v-if="newToken" class="rounded-lg border border-lb-green/40 bg-lb-green/10 p-4">
                        <p class="font-semibold">{{ t('apiTokens.newTokenTitle') }}</p>
                        <p class="mt-1 text-sm text-muted-foreground">{{ t('apiTokens.newTokenBody') }}</p>
                        <div class="mt-3 flex min-w-0 items-center gap-2">
                            <code class="min-w-0 flex-1 truncate rounded bg-lb-panel px-2 py-1.5 font-mono text-xs">{{ newToken }}</code>
                            <Button type="button" size="sm" variant="outline" @click="copy(newToken)">
                                {{ copied ? t('apiTokens.copied') : t('apiTokens.copy') }}
                            </Button>
                        </div>
                    </div>

                    <form @submit.prevent="create" class="space-y-3">
                        <div class="grid gap-2">
                            <Label for="token-name">{{ t('apiTokens.name') }}</Label>
                            <Input id="token-name" v-model="form.name" required maxlength="60" :placeholder="t('apiTokens.namePlaceholder')" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <Button type="submit" :disabled="form.processing">{{ t('apiTokens.create') }}</Button>
                    </form>

                    <section class="space-y-3">
                        <h3 class="text-sm font-semibold">{{ t('apiTokens.list') }}</h3>
                        <p v-if="tokens.length === 0" class="text-sm text-muted-foreground">{{ t('apiTokens.empty') }}</p>
                        <ul v-else class="divide-y divide-lb-line/30 rounded-lg border border-lb-line/30">
                            <li v-for="token in tokens" :key="token.id" class="flex items-center justify-between gap-3 p-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium">{{ token.name }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ token.lastUsedAt ? t('apiTokens.lastUsed', { date: fecha(token.lastUsedAt) }) : t('apiTokens.neverUsed') }}
                                    </p>
                                </div>
                                <Button type="button" size="sm" variant="destructive" @click="revoke(token)">{{ t('apiTokens.revoke') }}</Button>
                            </li>
                        </ul>
                    </section>

                    <section class="space-y-2">
                        <h3 class="text-sm font-semibold">{{ t('apiTokens.docsTitle') }}</h3>
                        <code class="block truncate rounded bg-lb-panel px-2 py-1.5 font-mono text-xs">{{ apiUrl }}</code>
                        <p class="text-sm text-muted-foreground">{{ t('apiTokens.docsBody') }}</p>
                    </section>
                </template>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
