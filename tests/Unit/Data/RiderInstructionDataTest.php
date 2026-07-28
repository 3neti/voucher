<?php

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\RiderInstructionData;
use LBHurtado\Voucher\Data\RiderStampData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\RiderContentFormat;
use LBHurtado\Voucher\Enums\RiderStampArtworkSource;
use LBHurtado\Voucher\Enums\RiderStampArtworkTreatment;
use LBHurtado\Voucher\Enums\RiderStampClaimMarker;
use LBHurtado\Voucher\Enums\RiderStampClaimMarkerPosition;
use LBHurtado\Voucher\Enums\RiderStampCopySource;
use LBHurtado\Voucher\Enums\RiderStampFit;
use LBHurtado\Voucher\Enums\RiderStampPosition;
use LBHurtado\Voucher\Enums\RiderStampSource;
use LBHurtado\Voucher\Enums\RiderStampTheme;

it('hydrates and serializes format-aware Rider instructions with a structured Stamp', function () {
    $rider = RiderInstructionData::from([
        'message' => '**Thank you** for claiming.',
        'url' => 'https://example.com/claim',
        'splash' => '# Welcome',
        'message_format' => 'markdown',
        'splash_format' => 'markdown',
        'stamp' => [
            'source' => 'splash',
            'title' => 'A gift for you',
            'description' => 'Open this Pay Code to claim.',
            'fit' => 'contain',
            'position' => 'top',
            'scrim' => 28,
            'theme' => 'dark',
            'version' => 2,
            'artwork_source' => 'url',
            'artwork_treatment' => 'artwork',
            'copy_source' => 'splash',
            'show_logo' => true,
            'show_tagline' => false,
            'claim_marker' => 'both',
            'claim_marker_position' => 'bottom_right',
        ],
    ]);

    expect($rider->message_format)->toBe(RiderContentFormat::Markdown)
        ->and($rider->splash_format)->toBe(RiderContentFormat::Markdown)
        ->and($rider->stamp)->toBeInstanceOf(RiderStampData::class)
        ->and($rider->stamp->source)->toBe(RiderStampSource::Splash)
        ->and($rider->stamp->fit)->toBe(RiderStampFit::Contain)
        ->and($rider->stamp->position)->toBe(RiderStampPosition::Top)
        ->and($rider->stamp->theme)->toBe(RiderStampTheme::Dark)
        ->and($rider->stamp->artwork_source)->toBe(RiderStampArtworkSource::Url)
        ->and($rider->stamp->artwork_treatment)->toBe(RiderStampArtworkTreatment::Artwork)
        ->and($rider->stamp->copy_source)->toBe(RiderStampCopySource::Splash)
        ->and($rider->stamp->claim_marker)->toBe(RiderStampClaimMarker::Both)
        ->and($rider->stamp->claim_marker_position)->toBe(RiderStampClaimMarkerPosition::BottomRight)
        ->and($rider->toArray()['stamp'])->toMatchArray([
            'source' => 'splash',
            'fit' => 'contain',
            'position' => 'top',
            'scrim' => 28,
            'theme' => 'dark',
            'version' => 2,
            'artwork_source' => 'url',
            'artwork_treatment' => 'artwork',
            'copy_source' => 'splash',
            'show_logo' => true,
            'show_tagline' => false,
            'claim_marker' => 'both',
            'claim_marker_position' => 'bottom_right',
        ]);
});

it('hydrates legacy Rider Stamp v1 instructions without requiring composition fields', function () {
    $stamp = RiderStampData::from([
        'source' => 'url',
        'fit' => 'cover',
        'position' => 'center',
        'version' => 1,
    ]);

    expect($stamp->version)->toBe(RiderStampData::LEGACY_SCHEMA_VERSION)
        ->and($stamp->source)->toBe(RiderStampSource::Url)
        ->and($stamp->artwork_source)->toBeNull()
        ->and($stamp->artwork_treatment)->toBeNull()
        ->and($stamp->copy_source)->toBeNull()
        ->and($stamp->claim_marker)->toBeNull();
});

it('keeps legacy Rider instructions clean and backward compatible', function () {
    $instructions = VoucherInstructionsData::createFromAttribs([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => [
            'message' => 'Legacy message',
            'splash' => '<strong>Legacy splash</strong>',
            'og_source' => 'splash',
        ],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
    ]);

    expect($instructions->rider->message_format)->toBeNull()
        ->and($instructions->rider->splash_format)->toBeNull()
        ->and($instructions->rider->stamp)->toBeNull()
        ->and(data_get($instructions->toCleanArray(), 'rider.og_source'))->toBe('splash')
        ->and(data_get($instructions->toCleanArray(), 'rider'))->not->toHaveKeys([
            'message_format',
            'splash_format',
            'stamp',
        ]);
});

it('validates Rider Stamp policy fields before hydration', function (array $stamp) {
    VoucherInstructionsData::createFromAttribs([
        'cash' => [
            'amount' => 100,
            'currency' => 'PHP',
            'validation' => ['country' => 'PH'],
        ],
        'inputs' => ['fields' => []],
        'feedback' => [],
        'rider' => ['stamp' => $stamp],
        'count' => 1,
        'prefix' => 'PAY',
        'mask' => '****',
    ]);
})->throws(ValidationException::class)->with([
    'unknown source' => [['source' => 'signature']],
    'invalid fit' => [['fit' => 'stretch']],
    'invalid position' => [['position' => 'everywhere']],
    'invalid scrim' => [['scrim' => 101]],
    'unsupported version' => [['version' => 3]],
    'invalid artwork source' => [['artwork_source' => 'remote_page']],
    'invalid artwork treatment' => [['artwork_treatment' => 'iframe']],
    'invalid copy source' => [['copy_source' => 'provider_payload']],
    'invalid claim marker' => [['claim_marker' => 'editable_url']],
    'invalid claim marker position' => [['claim_marker_position' => 'everywhere']],
]);
