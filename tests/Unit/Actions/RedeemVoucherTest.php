<?php

use LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedeemerAndCash;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LBHurtado\Voucher\Actions\RedeemVoucher;
use FrittenKeeZ\Vouchers\Facades\Vouchers;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;
use LBHurtado\Voucher\Exceptions\UnknownExecutionDriverException;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\ValidateRedemptionContract;
use LBHurtado\Voucher\Services\ExecutionDriverRegistry;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('uses the configured redemption pipeline in the intended order', function () {
    expect(config('voucher-pipeline.post-redemption'))->toBe([
        ValidateRedeemerAndCash::class,
        ValidateRedemptionContract::class,
        DisburseCash::class,
    ]);
});

it('redeems a valid voucher successfully', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    $result = RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->metadata)->toHaveKey('disbursement');
});

it('returns false when the voucher has already been redeemed', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();
});

it('returns false when the voucher is expired', function () {
    $voucher = issueVoucher();
    $voucher->expires_at = now()->subMinute();
    $voucher->save();

    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();
});

it('returns false when the voucher has not started yet', function () {
    $voucher = issueVoucher();
    $voucher->starts_at = now()->addMinute();
    $voucher->save();

    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeFalse();
});

it('returns false when the voucher code does not exist', function () {
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, 'NOT-A-REAL-CODE'))->toBeFalse();
});

it('keeps redemption successful but records a pending disbursement on downstream provider failure', function () {
    fakePayoutProvider()->willReturnFailedResult(transactionId: 'TXN-FAIL-001');

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    $result = RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($result)->toBeTrue()
        ->and($voucher->redeemed_at)->not->toBeNull()
        ->and($voucher->metadata)->toHaveKey('disbursement')
        ->and($voucher->metadata['disbursement']['status'])->toBe('pending')
        ->and($voucher->metadata['disbursement']['requires_reconciliation'])->toBeTrue();
});

it('passes redemption metadata to the voucher facade', function () {
    $contact = makeContactForRedemption();
    $code = 'TEST-CODE';

    $meta = [
        'ip' => '127.0.0.1',
        'channel' => 'sms',
    ];

    Vouchers::shouldReceive('redeem')
        ->once()
        ->with(
            $code,
            $contact,
            ['redemption' => $meta]
        )
        ->andReturnTrue();

    expect(RedeemVoucher::run($contact, $code, $meta))->toBeTrue();
});

it('records disbursement metadata after successful redemption', function () {
    fakePayoutProvider()->willReturnSuccessfulResult();

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    RedeemVoucher::run($contact, $voucher->code);
    $voucher->refresh();

    expect($voucher->metadata)->toHaveKey('disbursement');
});

it('rethrows unexpected exceptions', function () {
    $contact = makeContactForRedemption();

    Vouchers::shouldReceive('redeem')
        ->once()
        ->andThrow(new RuntimeException('Unexpected failure'));

    expect(fn () => RedeemVoucher::run($contact, 'ANY-CODE'))
        ->toThrow(RuntimeException::class, 'Unexpected failure');
});

it('hydrates an explicit default execution instruction through the public redemption path', function () {
    $driver = new RecordingExecutionDriver('default');
    app(ExecutionDriverRegistry::class)->register('default', $driver);

    $voucher = issueVoucherWithExecutionDriver('default');
    $contact = makeContactForRedemption();

    expect(app(\LBHurtado\Voucher\Contracts\RedeemsVouchers::class)->handle($contact, $voucher->code, ['channel' => 'sms']))
        ->toBeTrue()
        ->and($driver->contexts)->toHaveCount(1)
        ->and($driver->contexts[0]->voucher?->is($voucher))->toBeTrue()
        ->and($driver->contexts[0]->voucherCode)->toBe((string) $voucher->code)
        ->and($driver->contexts[0]->meta)->toBe(['channel' => 'sms'])
        ->and($driver->contexts[0]->instruction?->driver)->toBe('default');
});

it('hydrates a settlement envelope execution instruction through the public redemption path', function () {
    $driver = new RecordingExecutionDriver('settlement_envelope');
    app(ExecutionDriverRegistry::class)->register('settlement_envelope', $driver);

    $voucher = issueVoucherWithExecutionDriver('settlement_envelope');
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue()
        ->and($driver->contexts)->toHaveCount(1)
        ->and($driver->contexts[0]->voucher?->is($voucher))->toBeTrue()
        ->and($driver->contexts[0]->instruction?->driver)->toBe('settlement_envelope');
});

it('hydrates a stored value execution instruction through the public redemption path', function () {
    $driver = new RecordingExecutionDriver('stored_value');
    app(ExecutionDriverRegistry::class)->register('stored_value', $driver);

    $voucher = issueVoucherWithExecutionDriver('stored_value');
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue()
        ->and($driver->contexts)->toHaveCount(1)
        ->and($driver->contexts[0]->voucher?->is($voucher))->toBeTrue()
        ->and($driver->contexts[0]->instruction?->driver)->toBe('stored_value');
});

it('fails closed for an unknown explicit execution driver through the public redemption path', function () {
    $defaultDriver = new RecordingExecutionDriver('default');
    app(ExecutionDriverRegistry::class)->register('default', $defaultDriver);

    $voucher = issueVoucherWithExecutionDriver('imaginary_driver');
    $contact = makeContactForRedemption();

    expect(fn () => RedeemVoucher::run($contact, $voucher->code))
        ->toThrow(UnknownExecutionDriverException::class, 'Unknown execution driver [imaginary_driver].')
        ->and($defaultDriver->contexts)->toHaveCount(0);
});

it('keeps legacy vouchers without execution instructions on the default public redemption path', function () {
    $driver = new RecordingExecutionDriver('default');
    app(ExecutionDriverRegistry::class)->register('default', $driver);

    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    expect(RedeemVoucher::run($contact, $voucher->code))->toBeTrue()
        ->and($driver->contexts)->toHaveCount(1)
        ->and($driver->contexts[0]->voucher?->is($voucher))->toBeTrue()
        ->and($driver->contexts[0]->instruction?->driver)->toBe('default');
});

function issueVoucherWithExecutionDriver(string $driver)
{
    return issueVoucher(validVoucherInstructions(overrides: [
        'execution' => [
            'driver' => $driver,
        ],
    ]));
}

class RecordingExecutionDriver implements ExecutionDriverContract
{
    /**
     * @var array<int, ExecutionContextData>
     */
    public array $contexts = [];

    public function __construct(
        private readonly string $key,
    ) {}

    public function key(): string
    {
        return $this->key;
    }

    public function execute(ExecutionContextData $context): ExecutionResultData
    {
        $this->contexts[] = $context;

        return ExecutionResultData::succeeded($this->key);
    }
}
