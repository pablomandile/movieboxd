<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('movieboxd:refresh-stale')->dailyAt('04:00');
Schedule::command('movieboxd:reconcile-aggregates')->dailyAt('04:30');
Schedule::command('movieboxd:prune-cache')->hourly();
// Después del demo:reset de las 03:30, así lo que la demo resiembra ya cuenta como usado.
Schedule::command('movieboxd:prune-catalog')->dailyAt('05:00');

// Solo en la demo (APP_DEMO=true): la deja como nueva todas las noches. Antes de
// reconcile-aggregates, que repasa los contadores después.
Schedule::command('demo:reset')->dailyAt('03:30')->when(fn () => config('demo.enabled'));
