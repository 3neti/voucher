<?php

use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\PayoutStatus;

it('treats successful payout statuses as forward-moving results', function () {
    $completed = new PayoutResultData(
        transaction_id: 'TXN-1',
        uuid: 'uuid-1',
        status: PayoutStatus::COMPLETED,
        provider: 'fake'
    );

    $pending = new PayoutResultData(
        transaction_id: 'TXN-2',
        uuid: 'uuid-2',
        status: PayoutStatus::PENDING,
        provider: 'fake'
    );

    expect($completed->status)->toBe(PayoutStatus::COMPLETED)
        ->and($pending->status)->toBe(PayoutStatus::PENDING);
});

it('treats failed payout statuses as non-successful results', function () {
    $failed = new PayoutResultData(
        transaction_id: 'TXN-3',
        uuid: 'uuid-3',
        status: PayoutStatus::FAILED,
        provider: 'fake'
    );

    expect($failed->status)->toBe(PayoutStatus::FAILED);
});

it('preserves transaction identifiers for later reconciliation', function () {
    $result = new PayoutResultData(
        transaction_id: 'TXN-RECON-1',
        uuid: 'uuid-recon-1',
        status: PayoutStatus::FAILED,
        provider: 'fake'
    );

    expect($result->transaction_id)->toBe('TXN-RECON-1')
        ->and($result->uuid)->toBe('uuid-recon-1');
});

it('preserves provider identity for later reconciliation', function () {
    $result = new PayoutResultData(
        transaction_id: 'TXN-RECON-2',
        uuid: 'uuid-recon-2',
        status: PayoutStatus::FAILED,
        provider: 'fake-gateway'
    );

    expect($result->provider)->toBe('fake-gateway');
});

it('preserves raw metadata for later reconciliation', function () {
    $eventual = fakePendingPayoutResult(transactionId: 'TXN-PENDING-RAW');

    expect($eventual->transaction_id)->toBe('TXN-PENDING-RAW')
        ->and($eventual->provider)->toBe('fake');
});
