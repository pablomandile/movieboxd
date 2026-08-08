<script setup lang="ts">
import type { SharedData } from '@/types';
import { useForm, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

// Components
import HeadingSmall from '@/components/HeadingSmall.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

const { t } = useI18n();
const page = usePage<SharedData>();

// Las cuentas de Google no tienen contraseña: se confirma escribiendo el usuario
const props = withDefaults(defineProps<{ hasPassword?: boolean }>(), { hasPassword: true });
const username = computed(() => page.props.auth.user?.username ?? '');

const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
    confirm_username: '',
});

const deleteUser = (e: Event) => {
    e.preventDefault();

    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => closeModal(),
        onError: () => passwordInput.value?.focus(),
        onFinish: () => form.reset(),
    });
};

const closeModal = () => {
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <div class="space-y-6">
        <HeadingSmall title="Delete account" description="Delete your account and all of its resources" />
        <div class="space-y-4 rounded-lg border border-red-100 bg-red-50 p-4 dark:border-red-200/10 dark:bg-red-700/10">
            <div class="relative space-y-0.5 text-red-600 dark:text-red-100">
                <p class="font-medium">Warning</p>
                <p class="text-sm">Please proceed with caution, this cannot be undone.</p>
            </div>
            <Dialog>
                <DialogTrigger as-child>
                    <Button variant="destructive">Delete account</Button>
                </DialogTrigger>
                <DialogContent>
                    <form class="space-y-6" @submit="deleteUser">
                        <DialogHeader class="space-y-3">
                            <DialogTitle>Are you sure you want to delete your account?</DialogTitle>
                            <DialogDescription>
                                Once your account is deleted, all of its resources and data will also be permanently deleted. Please enter your
                                password to confirm you would like to permanently delete your account.
                            </DialogDescription>
                        </DialogHeader>

                        <div v-if="props.hasPassword" class="grid gap-2">
                            <Label for="password" class="sr-only">Password</Label>
                            <Input id="password" type="password" name="password" ref="passwordInput" v-model="form.password" placeholder="Password" />
                            <InputError :message="form.errors.password" />
                        </div>

                        <div v-else class="grid gap-2">
                            <p class="text-sm text-muted-foreground">{{ t('auth.deleteWithoutPassword', { username }) }}</p>
                            <Label for="confirm_username" class="sr-only">{{ t('auth.confirmUsername') }}</Label>
                            <Input
                                id="confirm_username"
                                name="confirm_username"
                                v-model="form.confirm_username"
                                autocomplete="off"
                                :placeholder="username"
                            />
                            <InputError :message="form.errors.confirm_username" />
                        </div>

                        <DialogFooter>
                            <DialogClose as-child>
                                <Button variant="secondary" @click="closeModal"> Cancel </Button>
                            </DialogClose>

                            <Button variant="destructive" :disabled="form.processing">
                                <button type="submit">Delete account</button>
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </div>
    </div>
</template>
