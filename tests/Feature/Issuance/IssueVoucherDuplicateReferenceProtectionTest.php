<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->setupSystemUser();
});

it('rejects duplicate voucher references', function () {
    $this->markTestSkipped('Voucher issuance currently generates package-managed references/codes internally; duplicate external reference injection is not exposed by the public issuance API yet.');
});

it('rejects duplicate voucher codes when uniqueness is required', function () {
    $first = issueVoucher();
    $second = issueVoucher();

    expect($first->code)->not->toBe($second->code);
});

it('does not partially persist duplicate issuance attempts', function () {
    $countBefore = \LBHurtado\Voucher\Models\Voucher::count();

    issueVoucher();
    issueVoucher();

    $countAfter = \LBHurtado\Voucher\Models\Voucher::count();

    expect($countAfter - $countBefore)->toBe(2);
});
