<?php

it('contains no netbank namespace imports in voucher source', function () {
    $srcDir = realpath(__DIR__.'/../../../src');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
    $violations = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') continue;
        $content = file_get_contents($file->getPathname());
        if (preg_match('/use\s+LBHurtado\\\\PaymentGateway\\\\/', $content)) {
            $violations[] = str_replace($srcDir.'/', '', $file->getPathname());
        }
    }

    expect($violations)->toBeEmpty('Found payment-gateway imports in: '.implode(', ', $violations));
});

it('contains no payment-gateway namespace imports in voucher source', function () {
    $srcDir = realpath(__DIR__.'/../../../src');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
    $violations = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') continue;
        $content = file_get_contents($file->getPathname());
        if (str_contains($content, '3neti/payment-gateway') || str_contains($content, '3neti/emi-netbank')) {
            $violations[] = str_replace($srcDir.'/', '', $file->getPathname());
        }
    }

    expect($violations)->toBeEmpty();
});
