<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

use LBHurtado\Voucher\Tests\TestCase;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Tests\Fakes\FakePayoutProvider;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use LBHurtado\Contact\Models\Contact;

pest()->extend(TestCase::class)->in('Unit', 'Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function validVoucherInstructions(
    float $amount = 100.00,
    ?string $settlementRail = 'INSTAPAY',
    array $overrides = []
): VoucherInstructionsData {
    $data = array_replace_recursive([
        'cash' => [
            'amount' => $amount,
            'currency' => 'PHP',
            'settlement_rail' => $settlementRail,
            'validation' => [
                'secret' => null,
                'mobile' => null,
                'payable' => null,
                'country' => 'PH',
                'location' => null,
                'radius' => null,
            ],
        ],
        'inputs' => [
            'fields' => [],
        ],
        'feedback' => [
            'email' => 'example@example.com',
            'mobile' => '09171234567',
            'webhook' => 'http://example.com/webhook',
        ],
        'rider' => [
            'message' => null,
            'url' => null,
            'redirect_timeout' => null,
            'splash' => null,
            'splash_timeout' => null,
            'og_source' => null,
        ],
        'count' => 1,
        'prefix' => 'TEST',
        'mask' => '****',
        'ttl' => null,
        'metadata' => [
            'issuer_id' => (string) optional(auth()->user())->id,
            'issuer_name' => optional(auth()->user())->name,
            'issuer_email' => optional(auth()->user())->email,
            'created_at' => now()->toIso8601String(),
            'issued_at' => now()->toIso8601String(),
        ],
    ], $overrides);

    return VoucherInstructionsData::from($data);
}

/**
 * Backward-compatible alias while the test suite is being migrated.
 */
function validInstructions(
    float $amount = 100.00,
    ?string $settlementRail = 'INSTAPAY',
    array $overrides = []
): VoucherInstructionsData {
    return validVoucherInstructions($amount, $settlementRail, $overrides);
}

function issueVoucher(
    VoucherInstructionsData|array|null $instructions = null
) {
    $instructions ??= validVoucherInstructions();

    if (is_array($instructions)) {
        $instructions = VoucherInstructionsData::from($instructions);
    }

    return GenerateVouchers::run($instructions)->first();
}

function makeRedeemPayload(
    array $overrides = []
): array {
    return array_replace_recursive([
        'mobile' => '09171234567',
        'country' => 'PH',
        'location' => null,
        'bank_code' => 'GCASH',
        'account_number' => '09171234567',
        'secret' => null,
        'requested_amount' => null,
        'requested_amounts' => null,
        'slice_count' => null,
    ], $overrides);
}

function makeContactForRedemption(
    array $overrides = []
): Contact {
    $payload = makeRedeemPayload($overrides);

    return Contact::factory()->create([
        'mobile' => $payload['mobile'],
        'bank_account' => "{$payload['bank_code']}:{$payload['account_number']}",
    ]);
}

function fakePayoutProvider(): FakePayoutProvider
{
    /** @var \LBHurtado\Voucher\Tests\TestCase $test */
    $test = test();

    return $test->fakePayoutProvider()->reset();
}

function fakeSuccessfulPayoutResult(
    ?string $transactionId = null,
    ?string $uuid = null,
    ?string $provider = 'fake'
): PayoutResultData {
    return new PayoutResultData(
        transaction_id: $transactionId ?? 'TXN-SUCCESS',
        uuid: $uuid ?? (string) \Illuminate\Support\Str::uuid(),
        status: PayoutStatus::COMPLETED,
        provider: $provider,
    );
}

function fakeFailedPayoutResult(
    ?string $transactionId = null,
    ?string $uuid = null,
    ?string $provider = 'fake'
): PayoutResultData {
    return new PayoutResultData(
        transaction_id: $transactionId ?? 'TXN-FAILED',
        uuid: $uuid ?? (string) \Illuminate\Support\Str::uuid(),
        status: PayoutStatus::FAILED,
        provider: $provider,
    );
}

function fakePendingPayoutResult(
    ?string $transactionId = null,
    ?string $uuid = null,
    ?string $provider = 'fake'
): PayoutResultData
{
    return new PayoutResultData(
        transaction_id: $transactionId ?? 'TXN-PENDING',
        uuid: $uuid ?? (string)\Illuminate\Support\Str::uuid(),
        status: PayoutStatus::PENDING,
        provider: $provider,
    );
}
