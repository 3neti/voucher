<?php

use LBHurtado\Voucher\Data\ExternalMetadataData;

test('can create with all fields', function () {
    $data = ExternalMetadataData::from([
        'external_id' => 'EXT-001',
        'external_type' => 'game',
        'reference_id' => 'REF-123',
        'user_id' => 'USER-456',
        'custom' => ['level' => 5, 'score' => 1000],
    ]);

    expect($data->external_id)->toBe('EXT-001')
        ->and($data->external_type)->toBe('game')
        ->and($data->reference_id)->toBe('REF-123')
        ->and($data->user_id)->toBe('USER-456')
        ->and($data->custom)->toBe(['level' => 5, 'score' => 1000]);
});

test('can create with null fields', function () {
    $data = ExternalMetadataData::from([]);

    expect($data->external_id)->toBeNull()
        ->and($data->external_type)->toBeNull()
        ->and($data->reference_id)->toBeNull()
        ->and($data->user_id)->toBeNull()
        ->and($data->custom)->toBeNull();
});

test('can get custom field', function () {
    $data = ExternalMetadataData::from([
        'custom' => ['game_id' => 'GAME-123', 'level' => 5],
    ]);

    expect($data->getCustom('game_id'))->toBe('GAME-123')
        ->and($data->getCustom('level'))->toBe(5);
});

test('get custom returns default for missing field', function () {
    $data = ExternalMetadataData::from([]);

    expect($data->getCustom('missing', 'default'))->toBe('default')
        ->and($data->getCustom('missing'))->toBeNull();
});

test('has custom checks field existence', function () {
    $data = ExternalMetadataData::from([
        'custom' => ['game_id' => 'GAME-123'],
    ]);

    expect($data->hasCustom('game_id'))->toBeTrue()
        ->and($data->hasCustom('missing'))->toBeFalse();
});

test('with custom returns new instance', function () {
    $original = ExternalMetadataData::from([
        'external_id' => 'EXT-001',
        'custom' => ['level' => 5],
    ]);

    $modified = $original->withCustom('score', 1000);

    // Original unchanged
    expect($original->hasCustom('score'))->toBeFalse()
        ->and($original->custom)->toBe(['level' => 5]);

    // Modified has new field
    expect($modified->hasCustom('score'))->toBeTrue()
        ->and($modified->getCustom('score'))->toBe(1000)
        ->and($modified->external_id)->toBe('EXT-001');
});

test('with custom works on null custom', function () {
    $data = ExternalMetadataData::from([]);
    $modified = $data->withCustom('game_id', 'GAME-123');

    expect($modified->getCustom('game_id'))->toBe('GAME-123');
});

test('to array includes all fields', function () {
    $data = ExternalMetadataData::from([
        'external_id' => 'EXT-001',
        'external_type' => 'game',
        'user_id' => 'USER-456',
        'custom' => ['level' => 5],
    ]);

    $array = $data->toArray();

    expect($array['external_id'])->toBe('EXT-001')
        ->and($array['external_type'])->toBe('game')
        ->and($array['reference_id'])->toBeNull()
        ->and($array['user_id'])->toBe('USER-456')
        ->and($array['custom'])->toBe(['level' => 5]);
});

test('validates string length limits', function () {
    ExternalMetadataData::validateAndCreate([
        'external_id' => str_repeat('a', 256), // Max 255
    ]);
})->throws(\Illuminate\Validation\ValidationException::class);
