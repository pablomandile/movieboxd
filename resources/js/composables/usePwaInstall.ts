import { computed, onMounted, onUnmounted, ref } from 'vue';

const STANDALONE = '(display-mode: standalone)';

interface DeferredPrompt extends Event {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
}

declare global {
    interface Window {
        __pwaInstall?: { prompt: DeferredPrompt | null; installed: boolean };
    }
}

/** iOS y iPadOS Safari nunca disparan beforeinstallprompt: la instalación es manual. */
function detectIosSafari(): boolean {
    const ua = navigator.userAgent;
    const isIosDevice =
        /iPad|iPhone|iPod/.test(ua) ||
        // iPadOS se reporta como Mac; se distingue por el soporte táctil
        (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    // Chrome/Firefox/Edge en iOS usan WebKit pero tampoco pueden instalar
    return isIosDevice && !/CriOS|FxiOS|EdgiOS|OPiOS/.test(ua);
}

function detectInstalled(): boolean {
    return (
        window.matchMedia?.(STANDALONE).matches === true ||
        (window.navigator as Navigator & { standalone?: boolean }).standalone === true ||
        window.__pwaInstall?.installed === true
    );
}

export function usePwaInstall() {
    const isInstalled = ref(detectInstalled());
    const isIos = detectIosSafari();

    // Sigue en true aunque el prompt ya se haya consumido: así el botón cae en
    // las instrucciones manuales en vez de quedar muerto.
    const offered = ref(!!window.__pwaInstall?.prompt);

    const canInstall = computed(() => !isInstalled.value && (offered.value || isIos));

    const onInstallable = () => (offered.value = true);
    const onInstalled = () => (isInstalled.value = true);
    const onDisplayModeChange = (e: MediaQueryListEvent) => (isInstalled.value = e.matches);
    const mq = window.matchMedia?.(STANDALONE);

    onMounted(() => {
        window.addEventListener('pwa:installable', onInstallable);
        window.addEventListener('pwa:installed', onInstalled);
        mq?.addEventListener?.('change', onDisplayModeChange);
    });

    onUnmounted(() => {
        window.removeEventListener('pwa:installable', onInstallable);
        window.removeEventListener('pwa:installed', onInstalled);
        mq?.removeEventListener?.('change', onDisplayModeChange);
    });

    async function install(): Promise<'accepted' | 'dismissed' | 'manual'> {
        const deferred = window.__pwaInstall?.prompt;
        if (!deferred) return 'manual';

        window.__pwaInstall!.prompt = null; // el evento sirve una sola vez
        await deferred.prompt();
        const { outcome } = await deferred.userChoice;

        return outcome;
    }

    return { canInstall, isInstalled, isIos, install };
}
