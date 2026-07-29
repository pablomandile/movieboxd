<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const form = useForm({
    name: '',
    username: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const submit = () => {
    form.post(route('register'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
};
</script>

<template>
    <AuthBase :title="t('auth.registerTitle')" :description="t('auth.registerDescription')">
        <Head :title="t('nav.register')" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="name">{{ t('auth.name') }}</Label>
                    <Input
                        id="name"
                        type="text"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="name"
                        v-model="form.name"
                        :placeholder="t('auth.namePlaceholder')"
                    />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <Label for="username">{{ t('auth.username') }}</Label>
                    <Input
                        id="username"
                        type="text"
                        required
                        tabindex="2"
                        autocomplete="username"
                        v-model="form.username"
                        :placeholder="t('auth.usernamePlaceholder')"
                    />
                    <InputError :message="form.errors.username" />
                </div>

                <div class="grid gap-2">
                    <Label for="email">{{ t('auth.email') }}</Label>
                    <Input id="email" type="email" required tabindex="3" autocomplete="email" v-model="form.email" placeholder="email@example.com" />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <Label for="password">{{ t('auth.password') }}</Label>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabindex="4"
                        autocomplete="new-password"
                        v-model="form.password"
                        :placeholder="t('auth.password')"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="grid gap-2">
                    <Label for="password_confirmation">{{ t('auth.confirmPassword') }}</Label>
                    <Input
                        id="password_confirmation"
                        type="password"
                        required
                        tabindex="5"
                        autocomplete="new-password"
                        v-model="form.password_confirmation"
                        :placeholder="t('auth.confirmPassword')"
                    />
                    <InputError :message="form.errors.password_confirmation" />
                </div>

                <Button type="submit" class="mt-2 w-full" tabindex="6" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ t('auth.createAccount') }}
                </Button>
            </div>

            <div class="text-center text-sm text-muted-foreground">
                {{ t('auth.alreadyRegistered') }}
                <TextLink :href="route('login')" class="underline underline-offset-4" tabindex="7">{{ t('auth.logIn') }}</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
