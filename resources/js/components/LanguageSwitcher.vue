<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Globe } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage<SharedData>();
const current = computed(() => page.props.locale);

const locales = ['es', 'en'] as const;

function switchTo(locale: string) {
    if (locale === current.value) return;

    router.put(route('locale.update'), { locale }, { preserveScroll: true });
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger :as-child="true">
            <Button variant="ghost" size="icon" class="h-9 w-9 text-lb-text hover:text-white" :aria-label="t('locale.language')">
                <Globe class="size-5" />
            </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end" class="w-36">
            <DropdownMenuItem
                v-for="locale in locales"
                :key="locale"
                class="cursor-pointer"
                :class="{ 'text-lb-green': locale === current }"
                @click="switchTo(locale)"
            >
                {{ t(`locale.${locale}`) }}
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
