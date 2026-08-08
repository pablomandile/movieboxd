<script setup lang="ts">
import InstallAppButton from '@/components/InstallAppButton.vue';
import LanguageSwitcher from '@/components/LanguageSwitcher.vue';
import SiteLogo from '@/components/SiteLogo.vue';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetContent, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { getInitials } from '@/composables/useInitials';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Menu, Plus, Search, Shield, User as UserIcon } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);
const searchQuery = ref('');
const menuOpen = ref(false);

function submitSearch() {
    const q = searchQuery.value.trim();
    if (!q) return;

    menuOpen.value = false; // el sheet no se cierra solo al navegar por XHR
    router.get(route('search'), { q });
}

const navItems = computed(() => {
    const items = [{ title: t('nav.lists'), href: route('lists.index') }];

    if (user.value) {
        items.push({ title: t('nav.diary'), href: route('diary.index') }, { title: t('nav.watchlist'), href: route('watchlist.index') });
    }

    items.push({ title: t('nav.about'), href: route('about') });

    return items;
});
</script>

<template>
    <header class="border-b border-lb-line/30 bg-lb-bg">
        <div class="mx-auto flex h-[72px] max-w-[1120px] items-center gap-2 px-3 sm:gap-4 sm:px-4">
            <!-- Menú mobile -->
            <div class="lg:hidden">
                <Sheet v-model:open="menuOpen">
                    <SheetTrigger :as-child="true">
                        <Button variant="ghost" size="icon" class="h-9 w-9 text-lb-text">
                            <Menu class="h-5 w-5" />
                        </Button>
                    </SheetTrigger>
                    <SheetContent side="left" class="w-[280px] bg-lb-bg p-6">
                        <SheetTitle class="sr-only">Menu</SheetTitle>
                        <SheetHeader class="flex justify-start text-left">
                            <Link :href="route('home')"><SiteLogo /></Link>
                        </SheetHeader>

                        <!-- Buscador: en la barra del header no entra en mobile -->
                        <form @submit.prevent="submitSearch" class="relative mt-6">
                            <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-lb-muted" />
                            <input
                                v-model="searchQuery"
                                type="search"
                                :placeholder="t('nav.search')"
                                class="h-10 w-full rounded-full border-0 bg-lb-surface pl-9 pr-3 text-sm text-white placeholder:text-lb-muted focus:outline-none focus:ring-2 focus:ring-lb-blue/70"
                            />
                        </form>

                        <nav class="mt-4 flex flex-col space-y-1">
                            <Link
                                v-for="item in navItems"
                                :key="item.title"
                                :href="item.href"
                                class="rounded px-3 py-2 text-sm font-semibold uppercase tracking-[0.075em] text-lb-text hover:bg-accent hover:text-white"
                            >
                                {{ item.title }}
                            </Link>
                        </nav>

                        <!-- Instalar como PWA: acá siempre hay lugar para el texto completo -->
                        <div class="mt-4 border-t border-lb-line/30 pt-4">
                            <InstallAppButton inline />
                        </div>

                        <!-- El selector de idioma vive acá en mobile: en el header no entra
                             junto a los botones de ingresar y registrarse -->
                        <div class="mt-2 border-t border-lb-line/30 pt-4 sm:hidden">
                            <p class="mb-1 px-3 text-xs font-semibold uppercase tracking-[0.075em] text-lb-muted">
                                {{ t('locale.language') }}
                            </p>
                            <LanguageSwitcher inline />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>

            <Link :href="route('home')" class="shrink-0">
                <SiteLogo compact />
            </Link>

            <!-- Nav desktop -->
            <nav class="hidden flex-1 items-center gap-1 lg:flex">
                <Link
                    v-for="item in navItems"
                    :key="item.title"
                    :href="item.href"
                    class="rounded px-3 py-2 text-[0.8125rem] font-bold uppercase tracking-[0.075em] text-lb-text transition-colors hover:text-white"
                >
                    {{ item.title }}
                </Link>
            </nav>

            <div class="ml-auto flex items-center gap-2">
                <form @submit.prevent="submitSearch" class="relative hidden sm:block">
                    <Search class="pointer-events-none absolute left-3 top-1/2 size-4 -translate-y-1/2 text-lb-muted" />
                    <input
                        v-model="searchQuery"
                        type="search"
                        :placeholder="t('nav.search')"
                        class="h-9 w-40 rounded-full border-0 bg-lb-surface pl-9 pr-3 text-sm text-white transition-all placeholder:text-lb-muted focus:w-56 focus:outline-none focus:ring-2 focus:ring-lb-blue/70"
                    />
                </form>

                <!-- + LOG: lleva a la búsqueda para encontrar qué registrar -->
                <Button v-if="user" as-child class="hidden h-9 gap-1 px-4 text-[0.8125rem] font-bold uppercase tracking-[0.075em] sm:inline-flex">
                    <Link :href="route('search')">
                        <Plus class="size-4" />
                        {{ t('nav.log') }}
                    </Link>
                </Button>

                <!-- En mobile el botón vive dentro del menú hamburguesa (no entra acá) -->
                <div class="hidden sm:block">
                    <InstallAppButton />
                </div>

                <!-- En mobile vive dentro del menú hamburguesa. El wrapper es necesario:
                     DropdownMenu (radix) no propaga la clase a un elemento del DOM -->
                <div class="hidden sm:block">
                    <LanguageSwitcher />
                </div>

                <!-- Usuario -->
                <template v-if="user">
                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button variant="ghost" size="icon" class="relative size-10 w-auto rounded-full p-1">
                                <Avatar class="size-8 overflow-hidden rounded-full ring-1 ring-white/25">
                                    <AvatarImage v-if="user.avatar_path" :src="user.avatar_path" :alt="user.name" />
                                    <AvatarFallback class="rounded-full bg-lb-surface text-sm font-semibold text-white">
                                        {{ getInitials(user.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <DropdownMenuItem :as-child="true">
                                <Link class="block w-full cursor-pointer" :href="route('profiles.show', user.username)">
                                    <UserIcon class="mr-2 inline size-4" />
                                    {{ t('nav.profile') }}
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <template v-if="user.role === 'admin'">
                                <DropdownMenuItem :as-child="true">
                                    <Link class="block w-full cursor-pointer" :href="route('admin.dashboard')">
                                        <Shield class="mr-2 inline size-4" />
                                        {{ t('nav.admin') }}
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuSeparator />
                            </template>
                            <UserMenuContent :user="user" />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </template>
                <template v-else>
                    <!-- Textos cortos en mobile para que entren los dos botones -->
                    <Link
                        :href="route('login')"
                        class="whitespace-nowrap px-1.5 text-xs font-bold uppercase tracking-[0.05em] text-lb-text hover:text-white sm:px-2 sm:text-[0.8125rem] sm:tracking-[0.075em]"
                    >
                        <span class="sm:hidden">{{ t('nav.loginShort') }}</span>
                        <span class="hidden sm:inline">{{ t('nav.login') }}</span>
                    </Link>
                    <Link
                        :href="route('register')"
                        class="whitespace-nowrap rounded bg-lb-green-dark px-2 py-2 text-xs font-bold uppercase tracking-[0.05em] text-white hover:bg-lb-green sm:px-3 sm:text-[0.8125rem] sm:tracking-[0.075em]"
                    >
                        <span class="sm:hidden">{{ t('nav.registerShort') }}</span>
                        <span class="hidden sm:inline">{{ t('nav.register') }}</span>
                    </Link>
                </template>
            </div>
        </div>
    </header>
</template>
