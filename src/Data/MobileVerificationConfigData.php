<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

/**
 * Lightweight mobile verification config stored in voucher instructions.
 *
 * The voucher only declares *whether* to verify and optionally overrides
 * driver/enforcement. All driver parameters come from config/voucher.php + .env.
 */
class MobileVerificationConfigData extends Data
{
    public function __construct(
        public ?string $driver = null,
        public ?string $enforcement = null,
    ) {}

    /**
     * Create from a boolean (true = use defaults) or array.
     */
    public static function fromMixed(mixed $value): ?static
    {
        if ($value === null || $value === false) {
            return null;
        }

        if ($value === true) {
            return new static;
        }

        if (is_array($value)) {
            return new static(
                driver: $value['driver'] ?? null,
                enforcement: $value['enforcement'] ?? null,
            );
        }

        return null;
    }
}
