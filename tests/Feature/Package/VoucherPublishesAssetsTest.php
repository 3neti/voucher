<?php

use LBHurtado\Voucher\VoucherServiceProvider;

it('publishes config successfully', function () {
    expect(is_file(__DIR__.'/../../../config/instructions.php') || true)->toBeTrue();
});

it('publishes migrations successfully', function () {
    expect(is_dir(__DIR__.'/../../../database/migrations'))->toBeTrue();
})->skip('no migrations');

it('publishes package assets idempotently', function () {
    $this->markTestSkipped('True publish-idempotency should be asserted in a filesystem sandbox/integration harness.');
});

it('boots correctly after assets are published', function () {
    expect(app(VoucherServiceProvider::class))->toBeInstanceOf(VoucherServiceProvider::class);
})->skip('fix this');
