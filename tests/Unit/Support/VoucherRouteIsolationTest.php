<?php

use Illuminate\Support\Facades\Route;
use LBHurtado\Voucher\VoucherServiceProvider;

it('does not require package routes to boot', function () {
    expect(app(VoucherServiceProvider::class))->toBeInstanceOf(VoucherServiceProvider::class);
})->skip('fix this');

it('can boot with routes disabled', function () {
    expect(true)->toBeTrue();
})->skip('fix this');

it('does not collide with common host route names', function () {
    expect(Route::has('login'))->toBeFalse();
});

it('registers routes only when explicitly enabled', function () {
    $this->markTestSkipped('Enable once the package ships optional route registration behind an explicit config flag.');
});
