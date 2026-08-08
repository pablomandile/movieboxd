<script setup lang="ts">
// Logo Movieboxd: tres TVs retro con antenitas (teal/ámbar/coral) + wordmark
// compact: en pantallas chicas muestra solo los TVs (el wordmark no entra en el header)
withDefaults(defineProps<{ compact?: boolean }>(), { compact: false });

const colors = ['#4ECDC4', '#FFC857', '#FF6B6B'];
</script>

<template>
    <span class="flex items-center gap-2">
        <span class="flex items-end gap-0.5" aria-hidden="true">
            <svg v-for="color in colors" :key="color" viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="none">
                <!-- Antenas en V -->
                <path d="M6.5 2.5 12 8.5 M17.5 2.5 12 8.5" :stroke="color" stroke-width="2" stroke-linecap="round" />
                <!-- Gabinete -->
                <rect x="3" y="8" width="18" height="13.5" rx="2.5" :fill="color" />
                <!-- Pantalla -->
                <rect x="5.5" y="10.5" width="10" height="8.5" rx="1" fill="#14181C" />
                <!-- Perillas -->
                <circle cx="18.3" cy="12.8" r="1.1" fill="#14181C" />
                <circle cx="18.3" cy="16.4" r="1.1" fill="#14181C" />
            </svg>
        </span>
        <span class="text-xl font-extrabold tracking-tight text-white" :class="compact ? 'hidden sm:inline' : ''">Movieboxd</span>
    </span>
</template>
