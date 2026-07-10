<?php

declare(strict_types=1);

use LBHurtado\Cash\Models\Cash;
use LBHurtado\Voucher\Models\Voucher;

it('generates vouchers with float cash amounts without BrickMath warnings', function () {
    $this->setupSystemUser();

    $warnings = [];

    set_error_handler(function (int $severity, string $message, string $file, int $line) use (&$warnings): bool {
        if (! str_contains($message, 'Passing floats to BigNumber::of()')) {
            return false;
        }

        $warnings[] = compact('severity', 'message', 'file', 'line');

        return true;
    });

    try {
        $voucher = issueVoucher(validVoucherInstructions(amount: 25.0));
    } finally {
        restore_error_handler();
    }

    expect($warnings)->toBeEmpty()
        ->and($voucher)->toBeInstanceOf(Voucher::class)
        ->and($voucher->instructions->cash->amount)->toBe(25.0)
        ->and((float) data_get($voucher->metadata, 'instructions.cash.amount'))->toBe(25.0);

    $cash = $voucher->cash;

    expect($cash)->toBeInstanceOf(Cash::class)
        ->and($cash->getRawOriginal('amount'))->toBe(2500)
        ->and($cash->amount->getAmount()->__toString())->toBe('25.00')
        ->and($cash->amount->getCurrency()->getCurrencyCode())->toBe('PHP');
});
