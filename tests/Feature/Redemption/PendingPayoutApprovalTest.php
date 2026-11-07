<?php

use LBHurtado\EmiCore\Contracts\PendingPayoutApproval;
use LBHurtado\Voucher\Actions\RedeemVoucher;

it('preserves a pending payout approval instead of reporting a failed payout', function () {
    config()->set('voucher.payout.bubble_pending_approval', true);
    $this->setupSystemUser();
    $exception = new class extends RuntimeException implements PendingPayoutApproval
    {
        public function provider(): string
        {
            return 'test-provider';
        }

        public function requestId(): ?string
        {
            return 'synthetic-reference';
        }

        public function toApprovalMetadata(): array
        {
            return [];
        }
    };
    fakePayoutProvider()->willThrow($exception);
    $voucher = issueVoucher();

    try {
        RedeemVoucher::run(makeContactForRedemption(), $voucher->code);
        $caught = null;
    } catch (Throwable $caught) {
    }

    expect($caught)->toBe($exception);
});

it('retains ordinary payout failure handling', function () {
    $this->setupSystemUser();
    fakePayoutProvider()->willThrow(new RuntimeException('synthetic provider failure'));
    $voucher = issueVoucher();

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);

    expect(data_get($voucher->fresh()->metadata, 'disbursement.status'))->toBe('pending')
        ->and(data_get($voucher->fresh()->metadata, 'disbursement.requires_reconciliation'))->toBeTrue();
});

it('keeps legacy pending approval behavior when the option is not enabled', function () {
    $this->setupSystemUser();
    fakePayoutProvider()->willThrow(new class extends RuntimeException implements PendingPayoutApproval
    {
        public function provider(): string
        {
            return 'test-provider';
        }

        public function requestId(): ?string
        {
            return 'synthetic-reference';
        }

        public function toApprovalMetadata(): array
        {
            return [];
        }
    });
    $voucher = issueVoucher();

    RedeemVoucher::run(makeContactForRedemption(), $voucher->code);

    expect(data_get($voucher->fresh()->metadata, 'disbursement.status'))->toBe('pending');
});
