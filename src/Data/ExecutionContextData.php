<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Contact\Models\Contact;
use LBHurtado\Voucher\Models\Voucher;
use Spatie\LaravelData\Data;

class ExecutionContextData extends Data
{
    public function __construct(
        public ?Contact $contact,
        public string $voucherCode,
        public array $meta = [],
        public ?Voucher $voucher = null,
        public ?ExecutionInstructionData $instruction = null,
        public array $correlation = [],
    ) {
        $this->instruction ??= ExecutionInstructionData::from([]);
    }

    public static function fromRedemption(
        Voucher $voucher,
        Contact $contact,
        string $voucherCode,
        array $meta = [],
        array $correlation = [],
    ): self {
        return new self(
            contact: $contact,
            voucherCode: $voucherCode,
            meta: $meta,
            voucher: $voucher,
            instruction: self::instructionFromVoucher($voucher),
            correlation: $correlation,
        );
    }

    private static function instructionFromVoucher(Voucher $voucher): ExecutionInstructionData
    {
        $payload = data_get($voucher->metadata, 'instructions.execution');

        if (is_array($payload)) {
            return ExecutionInstructionData::from($payload);
        }

        if ($voucher->instructions instanceof VoucherInstructionsData) {
            return $voucher->instructions->executionInstruction();
        }

        return ExecutionInstructionData::from([]);
    }
}
