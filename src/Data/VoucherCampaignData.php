<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

final class VoucherCampaignData extends Data
{
    public function __construct(
        public string $campaign_reference,
        public string $campaign_revision,
        public string $template_id,
        public string $template_version,
        public string $definition_hash,
        public ?string $payment_recognition_reference = null,
    ) {}
}
