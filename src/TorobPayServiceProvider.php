<?php

namespace TorobPay;

use Illuminate\Support\ServiceProvider;

class TorobPayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../config/torobpay.php',
            'torobpay'
        );

        $this->app->singleton('torobpay', function ($app) {
            return new TorobPay();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/torobpay.php' => config_path('torobpay.php'),
        ], 'torobpay-config');

        $this->loadMigrationsFrom( __DIR__ . '/database/migrations' );
    }
}