<?php

use FrittenKeeZ\Vouchers\Facades\Vouchers;
use FrittenKeeZ\Vouchers\Models\Voucher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Actions\GenerateVouchers;
use LBHurtado\Voucher\Data\CashInstructionData;
use LBHurtado\Voucher\Data\CashValidationRulesData;
use LBHurtado\Voucher\Data\FeedbackInstructionData;
use LBHurtado\Voucher\Data\InputFieldsData;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Pipelines\RedeemedVoucher\DisburseCash;
use LBHurtado\Voucher\Tests\Models\User;
use LBHurtado\Wallet\Services\SystemUserResolverService;

uses(RefreshDatabase::class);

beforeEach(function () {
    Config::set('account.system_user.identifier', 'apple@hurtado.ph');
    Config::set('account.system_user.identifier_column', 'email');
    Config::set('account.system_user.model', User::class);
    $this->system = User::factory()->create(['email' => 'apple@hurtado.ph']);
    $this->system->wallet;
    $this->system->depositFloat(10000);

    $user = User::factory()->create();
    $user->mobile = '09178251991';
    $user->save();

    \LBHurtado\Wallet\Actions\TopupWalletAction::run($user, 1000);

    $this->actingAs($user);
});

dataset('voucher', function () {
    return [
        [fn () => GenerateVouchers::run(new VoucherInstructionsData(
            cash: new CashInstructionData(
                amount: 53.7,
                currency: 'PHP',
                validation: new CashValidationRulesData(
                    secret: '123456',
                    mobile: '09171234567',
                    country: 'PH',
                    location: 'Makati City',
                    radius: '1000m'
                )
            ),
            inputs: new InputFieldsData([
                VoucherInputField::EMAIL,
                VoucherInputField::MOBILE,
            ]),
            feedback: new FeedbackInstructionData(
                email: 'feedback@acme.com',
                mobile: '09171234567',
                webhook: 'https://acme.com/webhook',
            ),
            rider: new RiderInstructionData(
                message: 'Welcome!',
                url: 'https://acme.com/rider',
            ),
            count: 1, // Default count
            prefix: null, // Use default prefix from config
            mask: null,   // Use default mask from config
            ttl: null     // Use default TTL (12 hours) from action
        ))->first()],
    ];
});

it('disburse voucher invokes handler which in turn invokes the disburse cash pipeline', function ($voucher) {
    $contact = Contact::factory()->create(['mobile' => '09173011987']);
    $this->app->instance(SystemUserResolverService::class, $this->system);

    $spy = Mockery::spy(DisburseCash::class);
    $this->app->instance(DisburseCash::class, $spy);

    $success = Vouchers::redeem($voucher->code, $contact, ['bank_account' => 'BNORPHMMXXX:000661592316']);
    expect($success)->toBeTrue();

    $spy->shouldHaveReceived('handle')
        ->once()
        ->withArgs(function ($passedVoucher, $next) use ($voucher) {
            // The first arg is your Voucher model
            return $passedVoucher instanceof Voucher
                && $passedVoucher->is($voucher)
                // The second arg must be a Closure
                && is_callable($next);
        });
})->with('voucher');

it('disburses cash live', function ($voucher) {
    $cash = $voucher->getEntities(Cash::class)->first();
    expect((float) $cash->balanceFloat)->toBe(53.7);

    $contact = Contact::factory()->create(['mobile' => '09467438575']);
    //    $contact = Contact::factory()->create(['mobile' => '09173011987']);

    $success = Vouchers::redeem($voucher->code, $contact);
    //    $success = Vouchers::redeem($voucher->code, $contact, ['bank_account' => 'BNORPHMMXXX:000661592316']);
    expect($success)->toBeTrue();
    $cash = $voucher->getEntities(Cash::class)->first();
    $cash->wallet->refreshBalance();
    expect((float) $cash->wallet->balanceFloat)->toBe(53.7); // need to be confirmed to become zero
})->with('voucher')->skip();
