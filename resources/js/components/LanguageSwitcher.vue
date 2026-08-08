<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Globe } from 'lucide-vue-next';
import { computed } from 'vue';
import { useI18n } from 'vue-i18n';

// inline: lista plana (para el menú mobile, donde un dropdown dentro de otro no funciona bien)
withDefaults(defineProps<{ inline?: boolean }>(), { inline: false });

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
    <div v-if="inline" class="flex flex-col">
        <button
            v-for="locale in locales"
            :key="locale"
            type="button"
            class="rounded px-3 py-2 text-left text-sm text-lb-text hover:bg-accent hover:text-white"
            :class="{ 'font-semibold text-lb-green': locale === current }"
            @click="switchTo(locale)"
        >
            {{ t(`locale.${locale}`) }}
        </button>
    </div>

    <DropdownMenu v-else>
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
