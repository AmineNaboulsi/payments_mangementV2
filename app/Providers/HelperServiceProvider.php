<?php

namespace App\Providers;

use App\Helpers\CacheHelper;
use Illuminate\Support\ServiceProvider;

class HelperServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register our cache helper as a singleton
        $this->app->singleton('cache.helper', function ($app) {
            return new CacheHelper();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
