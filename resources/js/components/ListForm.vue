<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const props = withDefaults(
    defineProps<{
        initial?: { name: string; description: string | null; isRanked: boolean; isPublic: boolean };
        submitRoute: string;
        method?: 'post' | 'put';
    }>(),
    { method: 'post', initial: undefined },
);

const { t } = useI18n();

const form = useForm({
    name: props.initial?.name ?? '',
    description: props.initial?.description ?? '',
    is_ranked: props.initial?.isRanked ?? false,
    is_public: props.initial?.isPublic ?? true,
});

function submit() {
    if (props.method === 'put') {
        form.put(props.submitRoute, { preserveScroll: true });
    } else {
        form.post(props.submitRoute);
    }
}
</script>

<template>
    <form @submit.prevent="submit" class="flex max-w-lg flex-col gap-5">
        <div class="grid gap-2">
            <Label for="name">{{ t('lists.name') }}</Label>
            <Input id="name" v-model="form.name" type="text" required maxlength="120" />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="description">{{ t('lists.description') }}</Label>
            <textarea
                id="description"
                v-model="form.description"
                rows="3"
                class="w-full rounded border-0 bg-lb-surface p-3 text-sm text-white placeholder:text-lb-muted focus:outline-none focus:ring-2 focus:ring-lb-blue/70"
            ></textarea>
            <InputError :message="form.errors.description" />
        </div>

        <Label class="flex items-center gap-3 text-sm text-lb-text">
            <Checkbox v-model:checked="form.is_ranked" />
            {{ t('lists.ranked') }}
        </Label>

        <Label class="flex items-center gap-3 text-sm text-lb-text">
            <Checkbox v-model:checked="form.is_public" />
            {{ t('lists.public') }}
        </Label>

        <div>
            <Button type="submit" :disabled="form.processing" class="uppercase tracking-[0.075em]">
                <LoaderCircle v-if="form.processing" class="mr-1 h-4 w-4 animate-spin" />
                {{ t('log.save') }}
            </Button>
        </div>
    </form>
</template>
