import { createI18n } from 'vue-i18n';
import en from './en';
import es from './es';

export type AppLocale = 'es' | 'en';

export function createAppI18n(initialLocale: string) {
    return createI18n({
        legacy: false,
        locale: normalizeLocale(initialLocale),
        fallbackLocale: 'en',
        messages: { en, es },
    });
}

export function normalizeLocale(locale: string | undefined): AppLocale {
    return locale === 'en' ? 'en' : 'es';
}
