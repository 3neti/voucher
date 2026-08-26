<?php

use Illuminate\Support\ServiceProvider;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Contracts\ExecutionPipelineStepContract;
use LBHurtado\Voucher\Contracts\PayableCollectionExecutionGateway;
use LBHurtado\Voucher\Contracts\SettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Contracts\StoredValueExecutionGateway;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Data\ExecutionPipelineStateData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Exceptions\PayableCollectionRejectedException;
use LBHurtado\Voucher\Exceptions\SettlementEnvelopeNotReadyException;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRejectedException;
use LBHurtado\Voucher\Exceptions\StoredValueSpendRequiresOtpException;
use LBHurtado\Voucher\Exceptions\UnknownExecutionDriverException;
use LBHurtado\Voucher\Exceptions\UnknownExecutionPipelineStepException;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Voucher\Services\DefaultExecutionDriver;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;
use LBHurtado\Voucher\Services\ExecutionEngine;
use LBHurtado\Voucher\Services\ExecutionPipelineRuntime;
use LBHurtado\Voucher\Services\ExecutionPipelineStepRegistry;
use LBHurtado\Voucher\Services\NullPayableCollectionExecutionGateway;
use LBHurtado\Voucher\Services\NullSettlementEnvelopeExecutionGateway;
use LBHurtado\Voucher\Services\NullStoredValueExecutionGateway;
use LBHurtado\Voucher\Services\PayableCollectionExecutionDriver;
use LBHurtado\Voucher\Services\SettlementEnvelopeExecutionDriver;
use LBHurtado\Voucher\Services\StoredValueExecutionDriver;
use LBHurtado\Voucher\VoucherServiceProvider;

it('autoloads all public classes from the package namespace', function () {
    expect(class_exists(VoucherServiceProvider::class))->toBeTrue()
        ->and(class_exists(GenerateVouchers::class))->toBeTrue()
        ->and(class_exists(RedeemVoucher::class))->toBeTrue();
});

it('contains no production references to App namespaces', function () {
    $root = realpath(__DIR__.'/../../../src');
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $offenders = [];

    foreach ($rii as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (str_contains($source, 'App\\')) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBeEmpty();
});

it('exposes a discoverable service provider', function () {
    expect(is_subclass_of(VoucherServiceProvider::class, ServiceProvider::class))->toBeTrue();
});

it('has no broken classmap or psr4 package classes', function () {
    expect(class_exists(Voucher::class))->toBeTrue()
        ->and(class_exists(VoucherInstructionsData::class))->toBeTrue()
        ->and(class_exists(ExecutionInstructionData::class))->toBeTrue()
        ->and(class_exists(ExecutionContextData::class))->toBeTrue()
        ->and(class_exists(ExecutionResultData::class))->toBeTrue()
        ->and(class_exists(ExecutionPipelineStateData::class))->toBeTrue()
        ->and(interface_exists(ExecutionDriverContract::class))->toBeTrue()
        ->and(interface_exists(ExecutionPipelineStepContract::class))->toBeTrue()
        ->and(interface_exists(PayableCollectionExecutionGateway::class))->toBeTrue()
        ->and(interface_exists(SettlementEnvelopeExecutionGateway::class))->toBeTrue()
        ->and(interface_exists(StoredValueExecutionGateway::class))->toBeTrue()
        ->and(class_exists(UnknownExecutionDriverException::class))->toBeTrue()
        ->and(class_exists(UnknownExecutionPipelineStepException::class))->toBeTrue()
        ->and(class_exists(SettlementEnvelopeNotReadyException::class))->toBeTrue()
        ->and(class_exists(PayableCollectionRejectedException::class))->toBeTrue()
        ->and(class_exists(StoredValueSpendRejectedException::class))->toBeTrue()
        ->and(class_exists(StoredValueSpendRequiresOtpException::class))->toBeTrue()
        ->and(class_exists(DefaultExecutionDriver::class))->toBeTrue()
        ->and(class_exists(ExecutionDriverRegistry::class))->toBeTrue()
        ->and(class_exists(ExecutionEngine::class))->toBeTrue()
        ->and(class_exists(ExecutionPipelineRuntime::class))->toBeTrue()
        ->and(class_exists(ExecutionPipelineStepRegistry::class))->toBeTrue()
        ->and(class_exists(NullSettlementEnvelopeExecutionGateway::class))->toBeTrue()
        ->and(class_exists(NullPayableCollectionExecutionGateway::class))->toBeTrue()
        ->and(class_exists(NullStoredValueExecutionGateway::class))->toBeTrue()
        ->and(class_exists(SettlementEnvelopeExecutionDriver::class))->toBeTrue()
        ->and(class_exists(PayableCollectionExecutionDriver::class))->toBeTrue()
        ->and(class_exists(StoredValueExecutionDriver::class))->toBeTrue();
});
