<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
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

        // Behind Caddy the site is only reachable over HTTPS (the microphone needs it),
        // so make every generated URL and asset link https.
        if ($this->app->environment('production')) {
            URL::forceHttps();
        }
    }
}
