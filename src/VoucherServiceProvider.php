<?php

namespace LBHurtado\Voucher;

use Illuminate\Support\Number;
use Illuminate\Support\ServiceProvider;
use LBHurtado\ReportRegistry\Contracts\ReportResolverInterface;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Contracts\GeneratesVouchers;
use LBHurtado\Voucher\Contracts\PayableCollectionExecutionGateway;
use LBHurtado\Voucher\Contracts\RedeemsVouchers;
use LBHurtado\Voucher\Contracts\SettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;
use LBHurtado\Voucher\Providers\EventServiceProvider;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\Voucher\Services\ExecutionPipelineRuntime;
use LBHurtado\Voucher\Services\ExecutionPipelineStepRegistry;
use LBHurtado\Voucher\Services\NullPayableCollectionExecutionGateway;
use LBHurtado\Voucher\Services\NullSettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Services\NullStoredValueExecutionGateway;
use LBHurtado\Voucher\Services\PayableCollectionExecutionDriver;
use LBHurtado\Voucher\Services\RedemptionContractEngine;
use LBHurtado\Voucher\Services\SettlementEnvelopeExecutionDriver;
use LBHurtado\Voucher\Services\StoredValueExecutionDriver;
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

        $this->app->bind(GeneratesVouchers::class, GenerateVouchers::class);
        $this->app->bind(RedeemsVouchers::class, RedeemVoucher::class);
        $this->app->bind(ExecutionDriverContract::class, DefaultExecutionDriver::class);
        $this->app->bind(SettlementEnvelopeExecutionGateway::class, NullSettlementEnvelopeExecutionGateway::class);
        $this->app->bind(StoredValueExecutionGateway::class, NullStoredValueExecutionGateway::class);
        $this->app->bind(PayableCollectionExecutionGateway::class, NullPayableCollectionExecutionGateway::class);
        $this->app->singleton(ExecutionPipelineStepRegistry::class, fn ($app) => new ExecutionPipelineStepRegistry($app));
        $this->app->singleton(ExecutionPipelineRuntime::class);
        $this->app->singleton(ExecutionDriverRegistry::class, function ($app) {
            return (new ExecutionDriverRegistry($app))
                ->register('default', DefaultExecutionDriver::class)
                ->register('settlement_envelope', SettlementEnvelopeExecutionDriver::class)
                ->register('stored_value', StoredValueExecutionDriver::class)
                ->register('payable_collection', PayableCollectionExecutionDriver::class);
        });

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
        if (interface_exists(ReportResolverInterface::class)) {
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
