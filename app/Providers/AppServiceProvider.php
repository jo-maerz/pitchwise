<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('admin', fn (User $user) => $user->isAdmin());

        // Accounts cannot be created in bulk from one IP address, yet the hourly cap
        // still lets a whole class sign up from a school's single IP.
        RateLimiter::for('registration', fn (Request $request) => [
            Limit::perMinute(10)->by('minute:'.$request->ip()),
            Limit::perHour(60)->by('hour:'.$request->ip()),
        ]);

        // Behind Caddy the site is only reachable over HTTPS (the microphone needs it),
        // so make every generated URL and asset link https.
        if ($this->app->environment('production')) {
            URL::forceHttps();
        }
    }
}
