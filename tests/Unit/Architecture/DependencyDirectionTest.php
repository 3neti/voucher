<?php

it('voucher source depends only on emi-core for provider contracts', function () {
    $srcDir = realpath(__DIR__.'/../../../src');
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($srcDir));
    $emiImports = [];

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') continue;
        $content = file_get_contents($file->getPathname());
        preg_match_all('/use\s+(LBHurtado\\\\Emi\w+)\\\\/', $content, $matches);
        foreach ($matches[1] as $ns) {
            $emiImports[$ns] = true;
        }
    }

    // Only LBHurtado\EmiCore should appear
    $nonCore = array_filter(array_keys($emiImports), fn ($ns) => $ns !== 'LBHurtado\EmiCore');
    expect($nonCore)->toBeEmpty('Found non-emi-core EMI imports: '.implode(', ', $nonCore));
});
