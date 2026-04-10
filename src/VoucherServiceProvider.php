<?php

namespace LBHurtado\Voucher;

use LBHurtado\Voucher\Providers\EventServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Number;

class VoucherServiceProvider extends ServiceProvider
{
    /**
     * Register bindings or package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/instructions.php',
            'instructions'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../config/voucher-pipeline.php',
            'voucher-pipeline'
        );

        $this->mergeConfigFrom(
            __DIR__.'/../config/vouchers.php',
            'voucher'
        );

        $this->app->singleton(MobileVerification\MobileVerificationManager::class);

        // Register report driver source path (used by report:install-drivers)
        if (interface_exists(\LBHurtado\ReportRegistry\Contracts\ReportResolverInterface::class)) {
            $sources = $this->app['config']->get('report-registry.driver_sources', []);
            $sources[] = __DIR__.'/../resources/report-drivers';
            $this->app['config']->set('report-registry.driver_sources', $sources);
        }
    }

    /**
     * Bootstrap any package services.
     */
    public function boot(): void
    {
        $this->app->register(EventServiceProvider::class);

        Number::useCurrency('PHP');

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        $this->loadRoutesFrom(__DIR__.'/../routes/api.php');

        $this->publishes([
            __DIR__.'/../config/instructions.php' => config_path('instructions.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../config/voucher-pipeline.php' => config_path('voucher-pipeline.php'),
        ], 'config');

    }
}
