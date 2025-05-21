<?php

namespace App\Providers;

use App\Models\Payment;
use App\Observers\PaymentObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Force Laravel to use the Predis client
        $this->app->bind('redis', function ($app) {
            $config = $app->make('config')->get('redis');
            return new \Illuminate\Redis\RedisManager($app, 'predis', $config);
        });
        
        // Register our custom Redis service
        $this->app->singleton('redis.service', function ($app) {
            return new \App\Services\RedisService();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register observers
        Payment::observe(PaymentObserver::class);
    }
}
