<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;

final class VoucherPaymentDetailsData extends Data
{
    public function __construct(
        public ?string $description = null,
        public ?string $reference = null,
        #[DataCollectionOf(VoucherPaymentDetailFieldData::class)]
        public array $fields = [],
        #[DataCollectionOf(VoucherPaymentDocumentData::class)]
        public array $documents = [],
    ) {}
}
