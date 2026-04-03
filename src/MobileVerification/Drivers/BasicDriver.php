<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\MobileVerification\Drivers;

use LBHurtado\Voucher\MobileVerification\Concerns\NormalizesPhoneNumbers;
use LBHurtado\Voucher\MobileVerification\MobileVerificationDriverInterface;
use LBHurtado\Voucher\MobileVerification\MobileVerificationResult;

class BasicDriver implements MobileVerificationDriverInterface
{
    use NormalizesPhoneNumbers;

    public function verify(string $mobile, array $context = []): MobileVerificationResult
    {
        $mobile = trim($mobile);

        if ($mobile === '') {
            return MobileVerificationResult::fail('Mobile number is required.');
        }

        // Must contain at least 7 digits
        $digits = preg_replace('/[^0-9]/', '', $mobile);
        if (strlen($digits) < 7) {
            return MobileVerificationResult::fail('Invalid mobile number format.');
        }

        $normalized = $this->normalize($mobile);

        return MobileVerificationResult::pass($normalized);
    }
}
