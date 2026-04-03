<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Specifications;

use Illuminate\Support\Facades\Log;
use LBHurtado\Voucher\Data\RedemptionContext;
use LBHurtado\Voucher\MobileVerification\MobileVerificationManager;

class MobileVerificationSpecification implements RedemptionSpecificationInterface
{
    public function __construct(
        private readonly MobileVerificationManager $manager,
    ) {}

    public function passes(object $voucher, RedemptionContext $context): bool
    {
        // Read mobile_verification from voucher instructions
        $config = $voucher->instructions->cash->validation->mobile_verification ?? null;

        // No verification configured — pass (current behavior preserved)
        if ($config === null) {
            return true;
        }

        // Resolve driver and enforcement from voucher config (overrides) + env defaults
        $driverName = $config->driver;
        $enforcement = $this->manager->getEnforcement($config->enforcement);

        $result = $this->manager->verify($context->mobile, $driverName);

        if ($result->passed()) {
            return true;
        }

        // Soft enforcement: log warning but allow redemption
        if ($enforcement === 'soft') {
            Log::warning('Mobile verification failed (soft enforcement)', [
                'voucher_code' => $voucher->code ?? null,
                'mobile' => $context->mobile,
                'driver' => $driverName ?? $this->manager->getDefaultDriver(),
                'reason' => $result->reason,
            ]);

            return true;
        }

        // Strict enforcement: block redemption
        return false;
    }
}
