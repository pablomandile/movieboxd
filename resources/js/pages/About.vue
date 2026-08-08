<script setup lang="ts">
import SiteLogo from '@/components/SiteLogo.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Head } from '@inertiajs/vue3';
import { Globe, Mail } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const EMAIL = 'pablo.mandile@gmail.com';
const WEBSITE = 'https://bioinfo.pablomandile.com.ar/pablo';
</script>

<template>
    <AppLayout>
        <Head :title="t('about.title')" />

        <div class="mx-auto max-w-2xl">
            <div class="section-divider">
                <h1 class="section-heading">{{ t('about.title') }}</h1>
            </div>

            <div class="mt-6 rounded-lg bg-lb-surface p-6 sm:p-8">
                <SiteLogo class="mb-6" />

                <p class="font-serif text-lg leading-relaxed text-lb-text">
                    {{ t('about.description') }}
                </p>

                <div class="mt-8 border-t border-lb-line/30 pt-6">
                    <p class="text-xs font-bold uppercase tracking-[0.075em] text-lb-muted">
                        {{ t('about.createdBy') }}
                    </p>
                    <p class="mt-1 text-lg font-semibold text-white">Pablo Mandile</p>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:gap-6">
                        <a :href="`mailto:${EMAIL}`" class="inline-flex items-center gap-2 text-sm text-lb-text transition-colors hover:text-lb-blue">
                            <Mail class="size-4 shrink-0" />
                            <span class="break-all">{{ EMAIL }}</span>
                        </a>

                        <a
                            :href="WEBSITE"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="inline-flex items-center gap-2 text-sm text-lb-text transition-colors hover:text-lb-blue"
                        >
                            <Globe class="size-4 shrink-0" />
                            <span>Bioinfo</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
