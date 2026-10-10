<?php

namespace App\Providers;

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
        // Behind Caddy the site is only reachable over HTTPS (the microphone needs it),
        // so make every generated URL and asset link https.
        if ($this->app->environment('production')) {
            URL::forceHttps();
        }
    }
}
