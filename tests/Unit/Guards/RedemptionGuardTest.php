<?php

declare(strict_types=1);

use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\Guards\RedemptionGuard;
use LBHurtado\Voucher\Specifications\InputsSpecification;
use LBHurtado\Voucher\Specifications\KycSpecification;
use LBHurtado\Voucher\Specifications\LocationSpecification;
use LBHurtado\Voucher\Specifications\MobileSpecification;
use LBHurtado\Voucher\Specifications\MobileVerificationSpecification;
use LBHurtado\Voucher\Specifications\PayableSpecification;
use LBHurtado\Voucher\Specifications\SecretSpecification;
use LBHurtado\Voucher\Specifications\TimeLimitSpecification;
use LBHurtado\Voucher\Specifications\TimeWindowSpecification;

function redemptionGuardWith(array $results): RedemptionGuard
{
    $specification = static function (string $class, string $key) use ($results): object {
        $mock = Mockery::mock($class);
        $mock->shouldReceive('passes')->andReturn($results[$key] ?? true);

        return $mock;
    };

    return new RedemptionGuard(
        secretSpec: $specification(SecretSpecification::class, 'secret'),
        mobileSpec: $specification(MobileSpecification::class, 'mobile'),
        payableSpec: $specification(PayableSpecification::class, 'payable'),
        inputsSpec: $specification(InputsSpecification::class, 'inputs'),
        kycSpec: $specification(KycSpecification::class, 'kyc'),
        locationSpec: $specification(LocationSpecification::class, 'location'),
        timeWindowSpec: $specification(TimeWindowSpecification::class, 'time_window'),
        timeLimitSpec: $specification(TimeLimitSpecification::class, 'time_limit'),
        mobileVerificationSpec: $specification(MobileVerificationSpecification::class, 'mobile_verification'),
    );
}

function vendorVoucher(): object
{
    return (object) [
        'instructions' => (object) [
            'cash' => (object) [
                'validation' => (object) [
                    'payable' => 'STORE1',
                    'secret' => 'release-code',
                    'mobile' => '+639173011987',
                ],
            ],
            'inputs' => (object) [
                'fields' => ['name'],
            ],
        ],
    ];
}

it('composes vendor matching with all configured claim safeguards', function (): void {
    $guard = redemptionGuardWith([
        'payable' => true,
        'secret' => false,
        'mobile' => false,
        'inputs' => false,
    ]);

    $result = $guard->check(vendorVoucher(), new RedemptionContext(
        mobile: '+639173011987',
        secret: 'wrong',
        vendorAlias: 'STORE1',
    ));

    expect($result->passes)->toBeFalse()
        ->and($result->failures)->toContain('secret', 'mobile', 'inputs')
        ->not->toContain('payable');
});

it('reports vendor mismatch together with another failed safeguard', function (): void {
    $guard = redemptionGuardWith([
        'payable' => false,
        'secret' => false,
    ]);

    $result = $guard->check(vendorVoucher(), new RedemptionContext(
        mobile: '+639173011987',
        secret: 'wrong',
        vendorAlias: 'OTHER',
    ));

    expect($result->failures)->toContain('payable', 'secret');
});
