<script setup lang="ts">
import GoogleAuthButton from '@/components/GoogleAuthButton.vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AuthBase from '@/layouts/AuthLayout.vue';
import type { SharedData } from '@/types';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { LoaderCircle } from 'lucide-vue-next';
import { useI18n } from 'vue-i18n';

defineProps<{
    status?: string;
    canResetPassword: boolean;
}>();

const { t } = useI18n();

const form = useForm({
    email: '',
    password: '',
    remember: false,
});

const submit = () => {
    form.post(route('login'), {
        onFinish: () => form.reset('password'),
    });
};

// En la demo (APP_DEMO=true) se entra con una cuenta compartida.
const demo = usePage<SharedData>().props.demo;
const enterDemo = () => {
    if (!demo) return;
    form.email = demo.email;
    form.password = demo.password;
    submit();
};
</script>

<template>
    <AuthBase :title="t('auth.loginTitle')" :description="t('auth.loginDescription')">
        <Head :title="t('nav.login')" />

        <div v-if="status" class="mb-4 text-center text-sm font-medium text-lb-green">
            {{ status }}
        </div>

        <div v-if="demo" class="mb-6 rounded-lg border border-lb-green/40 bg-lb-green/10 p-4">
            <p class="font-semibold">{{ t('demo.title') }}</p>
            <p class="mt-1 text-sm text-muted-foreground">{{ t('demo.description') }}</p>
            <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt class="text-muted-foreground">{{ t('auth.email') }}</dt>
                <dd class="break-all font-medium">{{ demo.email }}</dd>
                <dt class="text-muted-foreground">{{ t('auth.password') }}</dt>
                <dd class="font-medium">{{ demo.password }}</dd>
            </dl>
            <Button type="button" class="mt-4 w-full" :disabled="form.processing" @click="enterDemo">
                <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                {{ t('demo.enter') }}
            </Button>
            <p class="mt-4 text-center text-xs text-muted-foreground">{{ t('demo.orManual') }}</p>
        </div>

        <GoogleAuthButton v-else class="mb-6" />

        <form @submit.prevent="submit" class="flex flex-col gap-6">
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <Label for="email">{{ t('auth.email') }}</Label>
                    <Input
                        id="email"
                        type="email"
                        required
                        autofocus
                        tabindex="1"
                        autocomplete="email"
                        v-model="form.email"
                        placeholder="email@example.com"
                    />
                    <InputError :message="form.errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-between">
                        <Label for="password">{{ t('auth.password') }}</Label>
                        <TextLink v-if="canResetPassword && !demo" :href="route('password.request')" class="text-sm" tabindex="5">
                            {{ t('auth.forgotPassword') }}
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        required
                        tabindex="2"
                        autocomplete="current-password"
                        v-model="form.password"
                        :placeholder="t('auth.password')"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <div class="flex items-center justify-between" tabindex="3">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" v-model:checked="form.remember" tabindex="4" />
                        <span>{{ t('auth.rememberMe') }}</span>
                    </Label>
                </div>

                <Button type="submit" class="mt-4 w-full" tabindex="4" :disabled="form.processing">
                    <LoaderCircle v-if="form.processing" class="h-4 w-4 animate-spin" />
                    {{ t('auth.logIn') }}
                </Button>
            </div>

            <div v-if="!demo" class="text-center text-sm text-muted-foreground">
                {{ t('auth.dontHaveAccount') }}
                <TextLink :href="route('register')" :tabindex="5">{{ t('auth.signUp') }}</TextLink>
            </div>
        </form>
    </AuthBase>
</template>
