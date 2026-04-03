<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\MobileVerification;

class MobileVerificationResult
{
    public function __construct(
        public readonly bool $valid,
        public readonly ?string $normalizedMobile = null,
        public readonly ?string $reason = null,
        public readonly array $meta = [],
    ) {}

    public static function pass(?string $normalizedMobile = null, array $meta = []): static
    {
        return new static(
            valid: true,
            normalizedMobile: $normalizedMobile,
            meta: $meta,
        );
    }

    public static function fail(string $reason, ?string $normalizedMobile = null, array $meta = []): static
    {
        return new static(
            valid: false,
            normalizedMobile: $normalizedMobile,
            reason: $reason,
            meta: $meta,
        );
    }

    public function passed(): bool
    {
        return $this->valid;
    }

    public function failed(): bool
    {
        return ! $this->valid;
    }
}
