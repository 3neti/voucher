<?php

use LBHurtado\Voucher\Data\RiderInstructionData;

it('validates and serializes rider instruction data', function () {
    $data = RiderInstructionData::from([
        'message' => 'Thank you for claiming!',
        'url' => 'https://acme.com/redirect',
    ]);
    expect($data->message)->toBe('Thank you for claiming!');
    expect($data->url)->toBe('https://acme.com/redirect');
});

it('can create rider with splash fields only', function () {
    $data = RiderInstructionData::from([
        'message' => null,
        'url' => null,
        'redirect_timeout' => null,
        'splash' => '# Welcome!',
        'splash_timeout' => 10,
    ]);

    expect($data->message)->toBeNull();
    expect($data->url)->toBeNull();
    expect($data->redirect_timeout)->toBeNull();
    expect($data->splash)->toBe('# Welcome!');
    expect($data->splash_timeout)->toBe(10);
});

it('serializes splash fields correctly', function () {
    $data = RiderInstructionData::from([
        'message' => null,
        'url' => null,
        'redirect_timeout' => null,
        'splash' => '# Welcome!\nThis is a test.',
        'splash_timeout' => 10,
    ]);

    $array = $data->toArray();

    expect($array)->toHaveKey('splash');
    expect($array)->toHaveKey('splash_timeout');
    expect($array['splash'])->toBe('# Welcome!\nThis is a test.');
    expect($array['splash_timeout'])->toBe(10);
});

it('creates rider with all fields including splash', function () {
    $data = RiderInstructionData::from([
        'message' => 'Test message',
        'url' => 'https://example.com',
        'redirect_timeout' => 5,
        'splash' => '# Welcome!',
        'splash_timeout' => 10,
    ]);

    expect($data->message)->toBe('Test message');
    expect($data->url)->toBe('https://example.com');
    expect($data->redirect_timeout)->toBe(5);
    expect($data->splash)->toBe('# Welcome!');
    expect($data->splash_timeout)->toBe(10);
});

it('handles all null fields without throwing errors', function () {
    $data = RiderInstructionData::from([
        'message' => null,
        'url' => null,
        'redirect_timeout' => null,
        'splash' => null,
        'splash_timeout' => null,
    ]);

    $array = $data->toArray();

    // All fields should be present in array, even if null
    expect($array)->toHaveKeys(['message', 'url', 'redirect_timeout', 'splash', 'splash_timeout']);
});

it('preserves splash content through json encode/decode', function () {
    $data = RiderInstructionData::from([
        'message' => null,
        'url' => null,
        'redirect_timeout' => null,
        'splash' => '# Welcome!\nThis is **bold** text.',
        'splash_timeout' => 10,
    ]);

    $json = json_encode($data->toArray());
    $decoded = json_decode($json, true);

    expect($decoded['splash'])->toBe('# Welcome!\nThis is **bold** text.');
    expect($decoded['splash_timeout'])->toBe(10);
});
