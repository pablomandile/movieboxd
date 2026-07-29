<script setup lang="ts">
import RatingStars from '@/components/RatingStars.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Loggable } from '@/types';
import { router } from '@inertiajs/vue3';
import { Heart, LoaderCircle } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    open: boolean;
    loggable: Loggable;
    hasLogged?: boolean;
    canLike?: boolean;
}>();

const emit = defineEmits<{
    (e: 'update:open', value: boolean): void;
}>();

const { t } = useI18n();

const today = new Date().toISOString().slice(0, 10);
const processing = ref(false);

const form = reactive({
    watched_on: today,
    rating: null as number | null,
    liked: false,
    is_rewatch: props.hasLogged ?? false,
    tags: '',
    review: '',
    contains_spoilers: false,
});

watch(
    () => props.open,
    (open) => {
        if (open) {
            form.watched_on = today;
            form.rating = null;
            form.liked = false;
            form.is_rewatch = props.hasLogged ?? false;
            form.tags = '';
            form.review = '';
            form.contains_spoilers = false;
        }
    },
);

function submit() {
    processing.value = true;

    router.post(
        route('diary.store'),
        {
            loggable_type: props.loggable.type,
            loggable_id: props.loggable.id,
            watched_on: form.watched_on,
            rating: form.rating,
            liked: form.liked,
            is_rewatch: form.is_rewatch,
            tags: form.tags
                .split(',')
                .map((tag) => tag.trim())
                .filter(Boolean),
            review: form.review || null,
            contains_spoilers: form.contains_spoilers,
        },
        {
            preserveScroll: true,
            onFinish: () => (processing.value = false),
            onSuccess: () => emit('update:open', false),
        },
    );
}
</script>

<template>
    <Dialog :open="open" @update:open="emit('update:open', $event)">
        <DialogContent class="bg-lb-surface sm:max-w-md">
            <DialogHeader>
                <DialogTitle class="text-white">
                    {{ t('log.title') }}
                    <span class="text-lb-text">— {{ loggable.name }}</span>
                </DialogTitle>
            </DialogHeader>

            <form @submit.prevent="submit" class="mt-2 flex flex-col gap-5">
                <div class="grid gap-2">
                    <Label for="watched_on">{{ t('log.watchedOn') }}</Label>
                    <Input id="watched_on" type="date" v-model="form.watched_on" :max="today" required class="w-44" />
                </div>

                <Label class="flex items-center gap-3 text-sm text-lb-text">
                    <Checkbox v-model:checked="form.is_rewatch" />
                    {{ t('log.rewatch') }}
                </Label>

                <div class="flex items-center gap-6">
                    <div>
                        <p class="mb-1 text-xs uppercase tracking-wide text-lb-muted">{{ t('actions.rate') }}</p>
                        <RatingStars v-model="form.rating" />
                    </div>
                    <div v-if="canLike">
                        <p class="mb-1 text-xs uppercase tracking-wide text-lb-muted">{{ t('actions.like') }}</p>
                        <button type="button" class="p-1" @click="form.liked = !form.liked">
                            <Heart class="size-6" :class="form.liked ? 'text-lb-orange' : 'text-lb-line'" fill="currentColor" />
                        </button>
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="review">{{ t('log.review') }}</Label>
                    <textarea
                        id="review"
                        v-model="form.review"
                        rows="4"
                        :placeholder="t('log.reviewPlaceholder')"
                        class="w-full rounded border-0 bg-lb-panel p-3 font-serif text-sm text-white placeholder:text-lb-muted focus:outline-none focus:ring-2 focus:ring-lb-blue/70"
                    ></textarea>
                    <Label v-if="form.review" class="flex items-center gap-3 text-sm text-lb-text">
                        <Checkbox v-model:checked="form.contains_spoilers" />
                        {{ t('log.containsSpoilers') }}
                    </Label>
                </div>

                <div class="grid gap-2">
                    <Label for="tags">{{ t('log.tags') }}</Label>
                    <Input id="tags" type="text" v-model="form.tags" :placeholder="t('log.tagsPlaceholder')" />
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="processing" class="uppercase tracking-[0.075em]">
                        <LoaderCircle v-if="processing" class="mr-1 h-4 w-4 animate-spin" />
                        {{ t('log.save') }}
                    </Button>
                </div>
            </form>
        </DialogContent>
    </Dialog>
</template>
