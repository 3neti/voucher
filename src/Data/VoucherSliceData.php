<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use DateTimeImmutable;
use InvalidArgumentException;
use Spatie\LaravelData\Data;

final class VoucherSliceData extends Data
{
    public function __construct(
        public string $id,
        public string $label,
        public int $amount_minor,
        public int $sequence,
        public ?string $claim_on = null,
        public ?string $claim_by = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_-]{0,79}$/', $this->id) !== 1) {
            throw new InvalidArgumentException('Voucher slice IDs must use lowercase letters, numbers, underscores, or hyphens.');
        }

        if (trim($this->label) === '' || mb_strlen($this->label) > 120) {
            throw new InvalidArgumentException('Voucher slice labels must contain between 1 and 120 characters.');
        }

        if ($this->amount_minor <= 0) {
            throw new InvalidArgumentException('Voucher slice amounts must be positive integer minor units.');
        }

        if ($this->sequence < 1) {
            throw new InvalidArgumentException('Voucher slice sequences must be positive integers.');
        }

        $claimOn = $this->instant($this->claim_on, 'claim_on');
        $claimBy = $this->instant($this->claim_by, 'claim_by');

        if ($claimOn !== null && $claimBy !== null && $claimBy < $claimOn) {
            throw new InvalidArgumentException('Voucher slice claim_by must not be before claim_on.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'id' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,79}$/'],
            'label' => ['required', 'string', 'min:1', 'max:120'],
            'amount_minor' => ['required', 'integer', 'min:1'],
            'sequence' => ['required', 'integer', 'min:1'],
            'claim_on' => ['nullable', 'date'],
            'claim_by' => ['nullable', 'date', 'after_or_equal:claim_on'],
        ];
    }

    private function instant(?string $value, string $field): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if (trim($value) === '') {
            throw new InvalidArgumentException("Voucher slice {$field} cannot be empty.");
        }

        try {
            return new DateTimeImmutable($value);
        } catch (\Throwable) {
            throw new InvalidArgumentException("Voucher slice {$field} must be a valid timestamp.");
        }
    }
}
