<script setup lang="ts">
import { copyToClipboard } from '@/lib/clipboard';
import type { SharedData } from '@/types';
import { router, usePage } from '@inertiajs/vue3';
import { Check, Copy, RefreshCw, UserPlus, X } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps<{
    listId: number;
    collaborators: { id: number; name: string; username: string; avatar_path: string | null }[];
}>();

const { t } = useI18n();
const page = usePage<SharedData>();

const open = ref(false);
const busy = ref(false);
const copied = ref(false);
const copyFailed = ref(false);
const inviteUrl = ref<string | null>(null);
const linkInput = ref<HTMLInputElement | null>(null);

// El backend devuelve el enlace por flash tras "Invitar" o "Regenerar"
watch(
    () => page.props.flash?.inviteUrl,
    (url) => {
        if (url) {
            inviteUrl.value = url;
            open.value = true;
            copied.value = false;
        }
    },
    { immediate: true },
);

const hasCollaborators = computed(() => props.collaborators.length > 0);

function request(routeName: 'lists.invite' | 'lists.invite.regenerate') {
    if (busy.value) return;
    busy.value = true;
    copied.value = false;

    router.post(route(routeName, props.listId), {}, { preserveScroll: true, onFinish: () => (busy.value = false) });
}

async function copy() {
    if (!inviteUrl.value) return;

    copyFailed.value = false;

    if (await copyToClipboard(inviteUrl.value)) {
        copied.value = true;
        setTimeout(() => (copied.value = false), 2500);

        return;
    }

    // Ni la API moderna ni execCommand: queda seleccionar el texto a mano
    linkInput.value?.select();
    copyFailed.value = true;
}

function revoke(collaborator: { id: number; name: string }) {
    if (!confirm(t('lists.confirmRevoke', { name: collaborator.name }))) return;

    router.delete(route('lists.collaborators.revoke', [props.listId, collaborator.id]), { preserveScroll: true });
}
</script>

<template>
    <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded border border-lb-line/60 px-3 py-1.5 text-[0.75rem] font-bold uppercase tracking-[0.075em] text-lb-text transition-colors hover:border-lb-blue hover:text-white"
        @click="inviteUrl ? (open = true) : request('lists.invite')"
    >
        <UserPlus class="size-3.5" />
        {{ t('lists.invite') }}
        <span v-if="hasCollaborators" class="text-lb-muted">({{ collaborators.length }})</span>
    </button>

    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4" @click.self="open = false">
            <div class="w-full max-w-md rounded-lg bg-lb-surface p-6 shadow-xl">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <h2 class="text-lg font-bold text-white">{{ t('lists.inviteTitle') }}</h2>
                    <button type="button" :aria-label="t('pwa.close')" class="text-lb-muted hover:text-white" @click="open = false">
                        <X class="size-5" />
                    </button>
                </div>

                <p class="text-sm leading-relaxed text-lb-text">{{ t('lists.inviteHelp') }}</p>

                <div v-if="inviteUrl" class="mt-4 flex gap-2">
                    <input
                        ref="linkInput"
                        :value="inviteUrl"
                        readonly
                        class="min-w-0 flex-1 rounded border border-lb-line/60 bg-lb-bg px-3 py-2 text-xs text-lb-text focus:outline-none focus:ring-2 focus:ring-lb-blue/70"
                        @focus="($event.target as HTMLInputElement).select()"
                    />
                    <button
                        type="button"
                        class="inline-flex shrink-0 items-center gap-1.5 rounded bg-lb-green-dark px-3 py-2 text-xs font-bold uppercase tracking-[0.075em] text-white transition-colors hover:bg-lb-green"
                        @click="copy"
                    >
                        <component :is="copied ? Check : Copy" class="size-3.5" />
                        {{ copied ? t('lists.copied') : t('lists.copy') }}
                    </button>
                </div>

                <p v-if="copyFailed" class="mt-2 text-xs text-lb-muted">{{ t('lists.copyManually') }}</p>

                <button
                    type="button"
                    :disabled="busy"
                    class="mt-3 inline-flex items-center gap-1.5 text-xs text-lb-muted transition-colors hover:text-white"
                    @click="request('lists.invite.regenerate')"
                >
                    <RefreshCw class="size-3.5" />
                    {{ t('lists.regenerateLink') }}
                </button>

                <!-- Miembros actuales -->
                <div v-if="hasCollaborators" class="mt-6 border-t border-lb-line/30 pt-4">
                    <h3 class="mb-2 text-xs font-bold uppercase tracking-[0.075em] text-lb-muted">
                        {{ t('lists.members') }}
                    </h3>
                    <ul class="space-y-1">
                        <li v-for="collaborator in collaborators" :key="collaborator.id" class="flex items-center justify-between gap-3">
                            <span class="min-w-0 truncate text-sm text-white">{{ collaborator.name }}</span>
                            <button
                                type="button"
                                class="shrink-0 text-xs font-semibold uppercase tracking-[0.075em] text-lb-muted transition-colors hover:text-lb-orange"
                                @click="revoke(collaborator)"
                            >
                                {{ t('lists.revoke') }}
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </Teleport>
</template>
