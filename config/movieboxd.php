<?php

return [
    /*
     * Cantidad de votos "a priori" del promedio ponderado: los títulos con
     * pocas calificaciones se amortiguan hacia la media global de la comunidad.
     * Configurable desde el panel de admin (setting rating.prior).
     */
    'rating_prior' => env('MOVIEBOXD_RATING_PRIOR', 30),
];
