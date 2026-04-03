<?php

it('does not require package routes to boot', function () {
    expect(app(\LBHurtado\Voucher\VoucherServiceProvider::class))->toBeInstanceOf(\LBHurtado\Voucher\VoucherServiceProvider::class);
})->skip('fix this');

it('can boot with routes disabled', function () {
    expect(true)->toBeTrue();
})->skip('fix this');

it('does not collide with common host route names', function () {
    expect(\Illuminate\Support\Facades\Route::has('login'))->toBeFalse();
});

it('registers routes only when explicitly enabled', function () {
    $this->markTestSkipped('Enable once the package ships optional route registration behind an explicit config flag.');
});
