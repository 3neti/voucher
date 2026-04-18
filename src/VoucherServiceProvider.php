<?php

namespace LBHurtado\Voucher;

use LBHurtado\Voucher\Providers\EventServiceProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Services\RedemptionContractEngine;
use LBHurtado\Voucher\Support\RedemptionEvidenceExtractor;
use LBHurtado\Voucher\Validators\FaceMatchRuleValidator;
use LBHurtado\Voucher\Validators\LocationRuleValidator;
use LBHurtado\Voucher\Validators\OtpRuleValidator;
use LBHurtado\Voucher\Validators\RequiredInputFieldsValidator;
use LBHurtado\Voucher\Validators\SelfieRuleValidator;
use LBHurtado\Voucher\Validators\SignatureRuleValidator;
use LBHurtado\Voucher\Validators\TimeRuleValidator;

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

        $this->app->singleton(RedemptionEvidenceExtractor::class);

        $this->app->singleton(RequiredInputFieldsValidator::class);
        $this->app->singleton(SignatureRuleValidator::class);
        $this->app->singleton(SelfieRuleValidator::class);
        $this->app->singleton(LocationRuleValidator::class);
        $this->app->singleton(OtpRuleValidator::class);
        $this->app->singleton(TimeRuleValidator::class);
        $this->app->singleton(FaceMatchRuleValidator::class);

        $this->app->singleton(RedemptionContractEngine::class, function ($app) {
            return new RedemptionContractEngine(
                extractor: $app->make(RedemptionEvidenceExtractor::class),
                validators: [
                    $app->make(RequiredInputFieldsValidator::class),
                    $app->make(SignatureRuleValidator::class),
                    $app->make(SelfieRuleValidator::class),
                    $app->make(LocationRuleValidator::class),
                    $app->make(OtpRuleValidator::class),
                    $app->make(TimeRuleValidator::class),
                    $app->make(FaceMatchRuleValidator::class),
                ],
            );
        });

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
