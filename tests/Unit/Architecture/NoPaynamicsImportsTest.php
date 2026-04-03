<?php

it('contains no paynamics namespace imports in voucher source', function () {
    $srcDir = realpath(__DIR__.'/../../../src');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
    $violations = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') continue;
        $content = file_get_contents($file->getPathname());
        if (preg_match('/use\s+LBHurtado\\\\EmiPaynamicsConstellation\\\\/', $content)) {
            $violations[] = str_replace($srcDir.'/', '', $file->getPathname());
        }
    }

    expect($violations)->toBeEmpty();
});
