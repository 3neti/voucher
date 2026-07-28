<?php

namespace LBHurtado\Voucher\Data;

use InvalidArgumentException;
use LBHurtado\Voucher\Enums\RiderStampFit;
use LBHurtado\Voucher\Enums\RiderStampPosition;
use LBHurtado\Voucher\Enums\RiderStampSource;
use LBHurtado\Voucher\Enums\RiderStampTheme;
use Spatie\LaravelData\Data;

class RiderStampData extends Data
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        public RiderStampSource $source = RiderStampSource::Automatic,
        public ?string $title = null,
        public ?string $description = null,
        public RiderStampFit $fit = RiderStampFit::Cover,
        public RiderStampPosition $position = RiderStampPosition::Center,
        public ?int $scrim = null,
        public RiderStampTheme $theme = RiderStampTheme::Automatic,
        public int $version = self::SCHEMA_VERSION,
    ) {
        if ($this->scrim !== null && ($this->scrim < 0 || $this->scrim > 100)) {
            throw new InvalidArgumentException('The Rider Stamp scrim must be between 0 and 100.');
        }

        if ($this->version !== self::SCHEMA_VERSION) {
            throw new InvalidArgumentException("Unsupported Rider Stamp version [{$this->version}].");
        }
    }
}
