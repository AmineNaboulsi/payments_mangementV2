<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class OptimizeApp extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:optimize';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Optimize the application for production';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Optimizing application for high traffic...');
        
        // Clear cache to start with a fresh state
        $this->call('cache:clear');
        $this->info('Cache cleared.');
        
        // Optimize route loading
        $this->call('route:cache');
        $this->info('Routes cached.');
        
        // Cache config for faster loading
        $this->call('config:cache');
        $this->info('Configuration cached.');
        
        // Cache views for faster rendering
        $this->call('view:cache');
        $this->info('Views cached.');
        
        // Run general optimization
        $this->call('optimize');
        $this->info('General optimization complete.');
        
        $this->info('Application has been optimized for production and high traffic!');
        $this->info('Remember to run "php artisan migrate" to apply database indexing for improved performance.');
    }
}
