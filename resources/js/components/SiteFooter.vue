<script setup lang="ts">
import { useI18n } from 'vue-i18n';

const { t } = useI18n();
const year = new Date().getFullYear();
</script>

<template>
    <footer class="mt-16 bg-lb-surface/60">
        <div class="mx-auto max-w-[1120px] px-4 py-8">
            <p class="text-sm text-lb-text">
                © {{ year }} Movieboxd.
                {{ t('footer.filmData') }}
                <a href="https://www.themoviedb.org/" target="_blank" rel="noopener noreferrer" class="font-semibold text-lb-blue hover:text-white"
                    >TMDB</a
                >.
            </p>
            <p class="mt-1 text-xs text-lb-muted">
                {{ t('footer.tmdbAttribution') }}
            </p>
            <!-- Condición de uso de los datos de disponibilidad -->
            <p class="mt-1 text-xs text-lb-muted">
                {{ t('footer.justwatchAttribution') }}
                <a href="https://www.justwatch.com/" target="_blank" rel="noopener noreferrer" class="text-lb-blue hover:text-white">JustWatch</a>.
            </p>
        </div>
    </footer>
</template>
