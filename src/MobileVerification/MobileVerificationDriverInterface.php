<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\MobileVerification;

interface MobileVerificationDriverInterface
{
    /**
     * Verify a mobile number against this driver's rules.
     *
     * @param  string  $mobile  The mobile number to verify
     * @param  array  $context  Additional context (driver config from config/voucher.php)
     */
    public function verify(string $mobile, array $context = []): MobileVerificationResult;
}
