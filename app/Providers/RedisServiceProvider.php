<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\RedisService;

class RedisServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Register Redis service as a singleton
        $this->app->singleton(RedisService::class, function ($app) {
            return new RedisService();
        });
        
        // Bind the redis service to the container for dependency injection
        $this->app->bind('redis.service', function ($app) {
            return $app->make(RedisService::class);
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
