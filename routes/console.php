<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('movieboxd:refresh-stale')->dailyAt('04:00');
Schedule::command('movieboxd:reconcile-aggregates')->dailyAt('04:30');
