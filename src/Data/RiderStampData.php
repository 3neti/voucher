<?php

namespace LBHurtado\Voucher\Data;

use InvalidArgumentException;
use LBHurtado\Voucher\Enums\RiderStampArtworkSource;
use LBHurtado\Voucher\Enums\RiderStampArtworkTreatment;
use LBHurtado\Voucher\Enums\RiderStampClaimMarker;
use LBHurtado\Voucher\Enums\RiderStampClaimMarkerPosition;
use LBHurtado\Voucher\Enums\RiderStampCopySource;
use LBHurtado\Voucher\Enums\RiderStampFit;
use LBHurtado\Voucher\Enums\RiderStampPosition;
use LBHurtado\Voucher\Enums\RiderStampSource;
use LBHurtado\Voucher\Enums\RiderStampTheme;
use Spatie\LaravelData\Data;

class RiderStampData extends Data
{
    public const LEGACY_SCHEMA_VERSION = 1;

    public const COMPOSITION_SCHEMA_VERSION = 2;

    public const SCHEMA_VERSION = 3;

    public function __construct(
        public RiderStampSource $source = RiderStampSource::Automatic,
        public ?string $title = null,
        public ?string $description = null,
        public RiderStampFit $fit = RiderStampFit::Cover,
        public RiderStampPosition $position = RiderStampPosition::Center,
        public ?int $scrim = null,
        public RiderStampTheme $theme = RiderStampTheme::Automatic,
        public int $version = self::COMPOSITION_SCHEMA_VERSION,
        public ?string $design_id = null,
        public ?int $design_version = null,
        public ?RiderStampArtworkSource $artwork_source = null,
        public ?RiderStampArtworkTreatment $artwork_treatment = null,
        public ?RiderStampCopySource $copy_source = null,
        public ?bool $show_logo = null,
        public ?bool $show_tagline = null,
        public ?RiderStampClaimMarker $claim_marker = null,
        public ?RiderStampClaimMarkerPosition $claim_marker_position = null,
    ) {
        if ($this->scrim !== null && ($this->scrim < 0 || $this->scrim > 100)) {
            throw new InvalidArgumentException('The Rider Stamp scrim must be between 0 and 100.');
        }

        if (! in_array($this->version, [self::LEGACY_SCHEMA_VERSION, self::COMPOSITION_SCHEMA_VERSION, self::SCHEMA_VERSION], true)) {
            throw new InvalidArgumentException("Unsupported Rider Stamp version [{$this->version}].");
        }

        if ($this->version === self::SCHEMA_VERSION && ($this->design_id === null || $this->design_version === null)) {
            throw new InvalidArgumentException('Rider Stamp v3 requires a design identity and version.');
        }

        if ($this->design_id !== null && ! preg_match('/^[a-z][a-z0-9-]{0,79}$/', $this->design_id)) {
            throw new InvalidArgumentException('The Rider Stamp design identifier is invalid.');
        }

        if ($this->design_version !== null && $this->design_version < 1) {
            throw new InvalidArgumentException('The Rider Stamp design version must be at least 1.');
        }
    }
}
