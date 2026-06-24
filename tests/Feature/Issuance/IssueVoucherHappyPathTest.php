<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\ApplyUsageLimits;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\CreateCashEntities;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\LogAuditTrail;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\MarkAsProcessed;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\NormalizeMetadata;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\NotifyBatchCreator;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\PopulateSettlementFields;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\RunFraudChecks;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\TriggerPostGenerationWorkflows;
use LBHurtado\Voucher\Pipelines\GeneratedVouchers\ValidateStructure;
use LBHurtado\Voucher\Pipelines\Voucher\CheckBalance;
use LBHurtado\Voucher\Pipelines\Voucher\EscrowAction;
use LBHurtado\Voucher\Pipelines\Voucher\PersistCash;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('uses the configured generation pipelines in the intended order', function () {
    expect(config('voucher-pipeline.post-generation'))->toBe([
        ValidateStructure::class,
        PopulateSettlementFields::class,
        NormalizeMetadata::class,
        RunFraudChecks::class,
        ApplyUsageLimits::class,
        CreateCashEntities::class,
        NotifyBatchCreator::class,
        LogAuditTrail::class,
        MarkAsProcessed::class,
        TriggerPostGenerationWorkflows::class,
    ])->and(config('voucher-pipeline.mint-cash'))->toBe([
        CheckBalance::class,
        EscrowAction::class,
        PersistCash::class,
    ]);
});

it('issues a voucher successfully', function () {
    $instructions = validInstructions();
    $vouchers = GenerateVouchers::run($instructions);

    expect($vouchers)->toHaveCount(1);
    expect($vouchers->first()->code)->toBeString()->not->toBeEmpty();
});

it('persists lifecycle fields during issuance', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();

    expect($voucher->exists)->toBeTrue();
    expect($voucher->expires_at)->not->toBeNull();
});

it('persists amount and remaining amount during issuance', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();
    $cash = $voucher->getEntities(\LBHurtado\Cash\Models\Cash::class)->first();

    expect($cash)->not->toBeNull();
    expect((float) $cash->amount->getAmount()->toFloat())->toBeGreaterThan(0);
});

it('completes post-generation processing and attaches minted cash', function () {
    $voucher = GenerateVouchers::run(validInstructions())->first();

    expect($voucher->refresh()->processed)->toBeTrue()
        ->and($voucher->getEntities(\LBHurtado\Cash\Models\Cash::class))->toHaveCount(1);
});

it('persists metadata during issuance', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();

    expect($voucher->metadata)->toBeArray();
    expect($voucher->metadata)->toHaveKey('instructions');
});

it('persists instructions during issuance', function () {
    $instructions = validInstructions();
    $voucher = GenerateVouchers::run($instructions)->first();

    expect($voucher->instructions)->not->toBeNull();
    expect($voucher->instructions->cash->amount)->toBeGreaterThan(0);
});
