<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | El proyecto no usa SSR: el SEO se resuelve renderizando la metadata
    | Open Graph server-side en app.blade.php (ver ARCHITECTURE.md §10).
    |
    */

    'ssr' => [

        'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),

        'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),

        'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE — el default del paquete es resource_path('js/Pages') con "P"
    | mayúscula, pero este proyecto (starter kit de Vue) usa js/pages en
    | minúscula. En Windows/macOS el filesystem es case-insensitive y la ruta
    | resuelve igual, pero en Linux (GitHub Actions) NO existe: los tests con
    | assertInertia fallan con "Inertia page component file [X] does not exist".
    | Por eso el case se fija acá explícitamente.
    |
    */

    'ensure_pages_exist' => false,

    'page_paths' => [

        resource_path('js/pages'),

    ],

    'page_extensions' => [

        'js',
        'jsx',
        'svelte',
        'ts',
        'tsx',
        'vue',

    ],

    'use_script_element_for_initial_page' => (bool) env('INERTIA_USE_SCRIPT_ELEMENT_FOR_INITIAL_PAGE', false),

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | Mismo case real que arriba, para que assertInertia encuentre los
    | componentes en un filesystem case-sensitive.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

        'page_paths' => [

            resource_path('js/pages'),

        ],

        'page_extensions' => [

            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',

        ],

    ],

    'history' => [

        'encrypt' => (bool) env('INERTIA_ENCRYPT_HISTORY', false),

    ],

];
