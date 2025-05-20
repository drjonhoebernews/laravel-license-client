<?php

namespace Cmapps\LaravelLicenseClient;

use Illuminate\Support\ServiceProvider;

class LicenseClientServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/license-client.php' => config_path('license-client.php'),
        ], 'license-client-config');
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/license-client.php', 'license-client');
    }
}
