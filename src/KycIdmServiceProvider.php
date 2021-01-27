<?php

namespace DigtlCo\KycIdm;

use Illuminate\Support\ServiceProvider;

class KycIdmServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->bind('kycIdm', function($app) {
            return new KycIdm();
        });
    }

    public function boot() 
    {
        if ($this->app->runningInConsole()) {
            if (! class_exists('CreateKycUserTable')) {
                $this->publishes([
                    __DIR__ . '/../database/migrations/create_kyc_user.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_kyc_user_table.php'),
                    __DIR__ . '/../database/migrations/create_kyc_log.php.stub' => database_path('migrations/' . date('Y_m_d_His', time()) . '_create_kyc_log_table.php'),
                ], 'migrations');
            }
        }
    }

}