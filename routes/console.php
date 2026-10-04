<?php

use Illuminate\Support\Facades\Schedule;

// Roll finished runs into per-pitch statistics for the dashboard.
Schedule::command('practice:aggregate-stats')->everyFiveMinutes()->withoutOverlapping();

// Remove expired player tokens from personal_access_tokens.
Schedule::command('sanctum:prune-expired --hours=24')->daily();
