<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark" style="background-color: #14181c">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#14181c">

        {{-- PWA --}}
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Movieboxd">
        <link rel="manifest" href="/manifest.webmanifest">

        {{-- Chrome dispara beforeinstallprompt antes de que monte Vue: si el listener
             viviera en un componente, el evento ya habría pasado y el botón no
             aparecería nunca. Por eso se captura acá, lo más arriba posible. --}}
        <script>
            (function () {
                window.__pwaInstall = { prompt: null, installed: false };

                window.addEventListener('beforeinstallprompt', function (e) {
                    e.preventDefault();
                    window.__pwaInstall.prompt = e;
                    window.dispatchEvent(new CustomEvent('pwa:installable'));
                });

                window.addEventListener('appinstalled', function () {
                    window.__pwaInstall.prompt = null;
                    window.__pwaInstall.installed = true;
                    window.dispatchEvent(new CustomEvent('pwa:installed'));
                });

                if ('serviceWorker' in navigator) {
                    window.addEventListener('load', function () {
                        navigator.serviceWorker.register('/sw.js').catch(function () {});
                    });
                }
            })();
        </script>

        <title inertia>{{ config('app.name', 'Movieboxd') }}</title>

        @php($meta = $page['props']['meta'] ?? [])
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $meta['title'] ?? config('app.name') }}">
        <meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
        <meta property="og:url" content="{{ url()->current() }}">
        @if (!empty($meta['description']))
            <meta property="og:description" content="{{ $meta['description'] }}">
            <meta name="description" content="{{ $meta['description'] }}">
        @endif
        @if (!empty($meta['image']))
            <meta property="og:image" content="{{ $meta['image'] }}">
            <meta name="twitter:card" content="summary_large_image">
        @else
            <meta name="twitter:card" content="summary">
        @endif

        {{-- ?v=1: el CDN de Hostinger cachea estáticos de URL fija 7 días --}}
        <link rel="icon" href="/favicon.svg?v=1" type="image/svg+xml">
        <link rel="icon" href="/favicon.ico?v=1" sizes="48x48">
        <link rel="icon" type="image/png" sizes="192x192" href="/icons/icon-192.png?v=1">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=1">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700,800|lora:400,400i,600,600i&display=swap" rel="stylesheet" />

        @routes
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
