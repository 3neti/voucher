<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\MobileVerification\Drivers;

use LBHurtado\Voucher\MobileVerification\Concerns\NormalizesPhoneNumbers;
use LBHurtado\Voucher\MobileVerification\MobileVerificationDriverInterface;
use LBHurtado\Voucher\MobileVerification\MobileVerificationResult;
use Propaganistas\LaravelPhone\PhoneNumber;

class CountriesDriver implements MobileVerificationDriverInterface
{
    use NormalizesPhoneNumbers;

    public function verify(string $mobile, array $context = []): MobileVerificationResult
    {
        $allowedCountries = $context['countries'] ?? ['PH'];

        if (empty($allowedCountries)) {
            return MobileVerificationResult::fail('No allowed countries configured.');
        }

        try {
            $phone = new PhoneNumber($mobile);
            $country = $phone->getCountry();

            // If country was detected, check against allowed list
            if ($country !== '' && $country !== null) {
                if (! in_array($country, $allowedCountries, true)) {
                    return MobileVerificationResult::fail(
                        sprintf('Mobile number country (%s) is not in the allowed list: %s.', $country, implode(', ', $allowedCountries)),
                        $this->normalize($mobile),
                        ['detected_country' => $country, 'allowed_countries' => $allowedCountries],
                    );
                }

                return MobileVerificationResult::pass(
                    $phone->formatE164(),
                    ['detected_country' => $country],
                );
            }
        } catch (\Throwable) {
            // Fall through to country-hint resolution below
        }

        // Country not detected — try each allowed country as a hint
        foreach ($allowedCountries as $tryCountry) {
            try {
                $phone = new PhoneNumber($mobile, $tryCountry);
                if ($phone->isValid()) {
                    return MobileVerificationResult::pass(
                        $phone->formatE164(),
                        ['detected_country' => $tryCountry],
                    );
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return MobileVerificationResult::fail(
            'Could not determine country for the mobile number.',
            $this->normalize($mobile),
        );
    }
}
