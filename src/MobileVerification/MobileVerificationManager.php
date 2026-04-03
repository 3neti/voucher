<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\MobileVerification;

use InvalidArgumentException;

class MobileVerificationManager
{
    /**
     * Verify a mobile number using the specified driver (or default).
     *
     * @param  string  $mobile  The mobile number to verify
     * @param  string|null  $driverName  Driver name override (null = use config default)
     * @param  string|null  $enforcement  Enforcement override (null = use config default)
     */
    public function verify(string $mobile, ?string $driverName = null, ?string $enforcement = null): MobileVerificationResult
    {
        $config = config('voucher.mobile_verification', []);

        $resolvedDriver = $driverName ?? ($config['default'] ?? 'basic');
        $driverConfig = $config['drivers'][$resolvedDriver] ?? null;

        if (! $driverConfig || ! isset($driverConfig['class'])) {
            throw new InvalidArgumentException("Mobile verification driver [{$resolvedDriver}] is not configured.");
        }

        $driverClass = $driverConfig['class'];

        if (! class_exists($driverClass)) {
            throw new InvalidArgumentException("Mobile verification driver class [{$driverClass}] does not exist.");
        }

        /** @var MobileVerificationDriverInterface $driver */
        $driver = new $driverClass;

        return $driver->verify($mobile, $driverConfig);
    }

    /**
     * Get the resolved enforcement mode.
     */
    public function getEnforcement(?string $override = null): string
    {
        if ($override !== null) {
            return $override;
        }

        return config('voucher.mobile_verification.enforcement', 'strict');
    }

    /**
     * Get the default driver name from config.
     */
    public function getDefaultDriver(): string
    {
        return config('voucher.mobile_verification.default', 'basic');
    }
}
