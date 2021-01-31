<?php

namespace DigtlCo\KycIdm;

use Illuminate\Support\ServiceProvider;

class KycIdmServiceProvider extends ServiceProvider
{

    public function register()
    {
        
    }

    public function boot() 
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ .'/../config/kycidm.php' => config_path('kycidm.php'),
            ], 'config');
        }
    }

}