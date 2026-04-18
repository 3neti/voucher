<?php

use LBHurtado\Voucher\Support\RedemptionEvidenceExtractor;
use LBHurtado\Voucher\Models\Voucher;
use LBHurtado\Contact\Models\Contact;

beforeEach(function () {
    $this->setupSystemUser();
});

/**
 * Attach a redeemer record with metadata to the voucher.
 * Adjust this helper if your package already has a shared one.
 */
function attachRedeemerMetadata(Voucher $voucher, Contact $contact, array $metadata = []): void
{
    $voucher->redeemers()->forceCreate([
        'redeemer_id' => $contact->getKey(),
        'redeemer_type' => $contact::class,
        'metadata' => $metadata,
    ]);

    $voucher->refresh();
}

it('extracts signature from inputs.signature', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    attachRedeemerMetadata($voucher, $contact, [
        'inputs' => [
            'signature' => 'data:image/png;base64,FAKE_SIGNATURE',
        ],
    ]);

    $evidence = app(RedemptionEvidenceExtractor::class)->extract($voucher);

    expect($evidence->signature)->toBe('data:image/png;base64,FAKE_SIGNATURE');
});

it('extracts selfie from inputs.selfie', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    attachRedeemerMetadata($voucher, $contact, [
        'inputs' => [
            'selfie' => 'data:image/jpeg;base64,FAKE_SELFIE',
        ],
    ]);

    $evidence = app(RedemptionEvidenceExtractor::class)->extract($voucher);

    expect($evidence->selfie)->toBe('data:image/jpeg;base64,FAKE_SELFIE');
});

it('extracts location from inputs.location.lat and inputs.location.lng', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    attachRedeemerMetadata($voucher, $contact, [
        'inputs' => [
            'location' => [
                'lat' => 14.5995,
                'lng' => 121.0288,
            ],
        ],
    ]);

    $evidence = app(RedemptionEvidenceExtractor::class)->extract($voucher);

    expect($evidence->latitude)->toBe(14.5995)
        ->and($evidence->longitude)->toBe(121.0288);
});

it('extracts kyc payload from inputs.kyc', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    $kyc = [
        'face_verification' => [
            'verified' => true,
            'face_match' => true,
            'match_confidence' => 0.95,
        ],
    ];

    attachRedeemerMetadata($voucher, $contact, [
        'inputs' => [
            'kyc' => $kyc,
        ],
    ]);

    $evidence = app(RedemptionEvidenceExtractor::class)->extract($voucher);

    expect($evidence->kyc)->toBeArray()
        ->and($evidence->kyc)->toBe($kyc);
});

it('extracts face verification fields from inputs.kyc.face_verification', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    attachRedeemerMetadata($voucher, $contact, [
        'inputs' => [
            'kyc' => [
                'face_verification' => [
                    'verified' => true,
                    'face_match' => true,
                    'match_confidence' => 0.95,
                    'verified_at' => '2026-04-18T10:30:00+08:00',
                    'failure_reason' => null,
                ],
            ],
        ],
    ]);

    $evidence = app(RedemptionEvidenceExtractor::class)->extract($voucher);

    expect($evidence->face_verification_verified)->toBeTrue()
        ->and($evidence->face_match)->toBeTrue()
        ->and($evidence->match_confidence)->toBe(0.95)
        ->and($evidence->face_verified_at)->not->toBeNull()
        ->and($evidence->face_verified_at?->toIso8601String())->toStartWith('2026-04-18T10:30:00')
        ->and($evidence->face_failure_reason)->toBeNull();
});

it('extracts otp from inputs.otp', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    attachRedeemerMetadata($voucher, $contact, [
        'inputs' => [
            'otp' => '123456',
        ],
    ]);

    $evidence = app(RedemptionEvidenceExtractor::class)->extract($voucher);

    expect($evidence->otp)->toBe('123456');
});

it('prefers redemption signature over inputs signature when both are present', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    attachRedeemerMetadata($voucher, $contact, [
        'redemption' => [
            'signature' => 'data:image/png;base64,REDEMPTION_SIGNATURE',
        ],
        'inputs' => [
            'signature' => 'data:image/png;base64,INPUT_SIGNATURE',
        ],
    ]);

    $evidence = app(RedemptionEvidenceExtractor::class)->extract($voucher);

    expect($evidence->signature)->toBe('data:image/png;base64,REDEMPTION_SIGNATURE');
});

it('prefers redemption selfie over inputs selfie when both are present', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    attachRedeemerMetadata($voucher, $contact, [
        'redemption' => [
            'selfie' => 'data:image/jpeg;base64,REDEMPTION_SELFIE',
        ],
        'inputs' => [
            'selfie' => 'data:image/jpeg;base64,INPUT_SELFIE',
        ],
    ]);

    $evidence = app(RedemptionEvidenceExtractor::class)->extract($voucher);

    expect($evidence->selfie)->toBe('data:image/jpeg;base64,REDEMPTION_SELFIE');
});

it('prefers redemption location over inputs location when both are present', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    attachRedeemerMetadata($voucher, $contact, [
        'redemption' => [
            'location' => [
                'lat' => 10.1111,
                'lng' => 20.2222,
            ],
        ],
        'inputs' => [
            'location' => [
                'lat' => 14.5995,
                'lng' => 121.0288,
            ],
        ],
    ]);

    $evidence = app(RedemptionEvidenceExtractor::class)->extract($voucher);

    expect($evidence->latitude)->toBe(10.1111)
        ->and($evidence->longitude)->toBe(20.2222);
});

it('prefers redemption otp over inputs otp when both are present', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    attachRedeemerMetadata($voucher, $contact, [
        'redemption' => [
            'otp' => [
                'value' => '654321',
            ],
        ],
        'inputs' => [
            'otp' => '123456',
        ],
    ]);

    $evidence = app(RedemptionEvidenceExtractor::class)->extract($voucher);

    expect($evidence->otp)->toBe('654321');
});

it('prefers redemption kyc over inputs kyc when both are present', function () {
    $voucher = issueVoucher();
    $contact = makeContactForRedemption();

    attachRedeemerMetadata($voucher, $contact, [
        'redemption' => [
            'kyc' => [
                'face_verification' => [
                    'verified' => false,
                    'face_match' => false,
                    'match_confidence' => 0.10,
                ],
            ],
        ],
        'inputs' => [
            'kyc' => [
                'face_verification' => [
                    'verified' => true,
                    'face_match' => true,
                    'match_confidence' => 0.95,
                ],
            ],
        ],
    ]);

    $evidence = app(RedemptionEvidenceExtractor::class)->extract($voucher);

    expect($evidence->kyc)->toBeArray()
        ->and($evidence->face_verification_verified)->toBeFalse()
        ->and($evidence->face_match)->toBeFalse()
        ->and($evidence->match_confidence)->toBe(0.10);
});