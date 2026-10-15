<script setup lang="ts">
// Logo Movieboxd. Por defecto el logo con nombre (sobre fondo oscuro).
// compact: en mobile muestra solo el ícono y desde sm el logo con nombre.
withDefaults(defineProps<{ compact?: boolean }>(), { compact: false });
</script>

<template>
    <span class="flex items-center">
        <template v-if="compact">
            <img src="/img/movieboxd-icono.webp" alt="Movieboxd" width="1024" height="1024" class="size-9 rounded-lg sm:hidden" />
            <img
                src="/img/movieboxd-logo-nombre-fondo-oscuro.webp"
                alt="Movieboxd"
                width="2788"
                height="776"
                class="hidden h-9 w-auto sm:block"
            />
        </template>
        <img
            v-else
            src="/img/movieboxd-logo-nombre-fondo-oscuro.webp"
            alt="Movieboxd"
            width="2788"
            height="776"
            class="h-10 w-auto"
        />
    </span>
</template>
