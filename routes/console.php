<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('practice:aggregate-stats')->everyFiveMinutes()->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')->daily();
