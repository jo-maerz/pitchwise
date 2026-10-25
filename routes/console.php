<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('practice:aggregate-stats')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Only where DEMO_PASSWORD is set: visitors share the demo accounts, so what they change is put back.
Schedule::command('practice:reset-demo')->dailyAt('03:00')->when(fn () => filled(config('practice.demo_password')));
