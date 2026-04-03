<?php

it('autoloads all public classes from the package namespace', function () {
    expect(class_exists(\LBHurtado\Voucher\VoucherServiceProvider::class))->toBeTrue()
        ->and(class_exists(\LBHurtado\Voucher\Actions\GenerateVouchers::class))->toBeTrue()
        ->and(class_exists(\LBHurtado\Voucher\Actions\RedeemVoucher::class))->toBeTrue();
});

it('contains no production references to App namespaces', function () {
    $root = realpath(__DIR__.'/../../../src');
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    $offenders = [];

    foreach ($rii as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $source = file_get_contents($file->getPathname());

        if (str_contains($source, 'App\\')) {
            $offenders[] = $file->getPathname();
        }
    }

    expect($offenders)->toBeEmpty();
});

it('exposes a discoverable service provider', function () {
    expect(is_subclass_of(\LBHurtado\Voucher\VoucherServiceProvider::class, \Illuminate\Support\ServiceProvider::class))->toBeTrue();
});

it('has no broken classmap or psr4 package classes', function () {
    expect(class_exists(\LBHurtado\Voucher\Models\Voucher::class))->toBeTrue()
        ->and(class_exists(\LBHurtado\Voucher\Data\VoucherInstructionsData::class))->toBeTrue();
});
