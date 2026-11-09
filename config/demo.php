<?php

/*
 * Modo demo: la copia de movieboxd.pablomandile.com.ar es la demo del portfolio.
 * Con APP_DEMO=true hay un usuario compartido (más unos amigos inventados) con
 * datos de ejemplo que se resiembran todas las noches (demo:reset), y nadie puede
 * registrarse ni cambiar la cuenta. En la productiva queda apagado y nada de esto actúa.
 */
return [
    'enabled' => (bool) env('APP_DEMO', false),
    'email' => 'demo@pablomandile.com.ar',
    'password' => 'demo1234',
];
