<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Data;

use InvalidArgumentException;
use JsonException;
use LBHurtado\Voucher\Enums\VoucherSlicePlanMode;
use LBHurtado\Voucher\Enums\VoucherSliceSelectionPolicy;
use LBHurtado\Voucher\Exceptions\UnsupportedVoucherSlicePlanSchemaException;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

final class VoucherSlicePlanData extends Data
{
    public const SCHEMA = 'voucher.slice-plan.v1';

    /**
     * @param  DataCollection<int, VoucherSliceData>  $slices
     */
    public function __construct(
        public string $schema,
        #[WithCast(EnumCast::class)]
        public VoucherSlicePlanMode $mode,
        #[WithCast(EnumCast::class)]
        public VoucherSliceSelectionPolicy $selection,
        public int $total_minor,
        public string $currency,
        #[DataCollectionOf(VoucherSliceData::class)]
        public DataCollection $slices,
        public ?int $max_slices = null,
        public ?int $min_amount_minor = null,
    ) {
        if ($this->schema !== self::SCHEMA) {
            throw UnsupportedVoucherSlicePlanSchemaException::forSchema($this->schema);
        }

        if ($this->total_minor <= 0) {
            throw new InvalidArgumentException('Voucher slice plan totals must be positive integer minor units.');
        }

        if (preg_match('/^[A-Z]{3}$/', $this->currency) !== 1) {
            throw new InvalidArgumentException('Voucher slice plan currency must be an uppercase ISO currency code.');
        }

        $this->assertUniqueRows();
        $this->assertModeInvariants();
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'schema' => ['required', 'string', 'min:1'],
            'mode' => ['required', 'string', 'in:'.implode(',', array_column(VoucherSlicePlanMode::cases(), 'value'))],
            'selection' => ['required', 'string', 'in:'.implode(',', array_column(VoucherSliceSelectionPolicy::cases(), 'value'))],
            'total_minor' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'size:3', 'uppercase'],
            'slices' => ['present', 'array'],
            'slices.*' => ['required', 'array'],
            'slices.*.id' => ['required', 'string', 'regex:/^[a-z][a-z0-9_-]{0,79}$/'],
            'slices.*.label' => ['required', 'string', 'min:1', 'max:120'],
            'slices.*.amount_minor' => ['required', 'integer', 'min:1'],
            'slices.*.sequence' => ['required', 'integer', 'min:1'],
            'slices.*.claim_on' => ['nullable', 'date'],
            'slices.*.claim_by' => ['nullable', 'date'],
            'max_slices' => ['nullable', 'integer', 'min:1'],
            'min_amount_minor' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function assertUniqueRows(): void
    {
        $ids = $this->slices->toCollection()->map(fn (VoucherSliceData $slice): string => $slice->id);
        $sequences = $this->slices->toCollection()->map(fn (VoucherSliceData $slice): int => $slice->sequence);

        if ($sequences->isEmpty()) {
            return;
        }

        if ($ids->unique()->count() !== $ids->count()) {
            throw new InvalidArgumentException('Voucher slice plan IDs must be unique.');
        }

        if ($sequences->unique()->count() !== $sequences->count()) {
            throw new InvalidArgumentException('Voucher slice plan sequences must be unique.');
        }

        if ($sequences->sort()->values()->all() !== range(1, $sequences->count())) {
            throw new InvalidArgumentException('Voucher slice plan sequences must be contiguous from one.');
        }
    }

    private function assertModeInvariants(): void
    {
        if ($this->mode === VoucherSlicePlanMode::Flexible) {
            if ($this->selection !== VoucherSliceSelectionPolicy::FlexibleAmount) {
                throw new InvalidArgumentException('Flexible slice plans require the flexible_amount selection policy.');
            }

            if ($this->slices->count() !== 0 || $this->max_slices === null || $this->min_amount_minor === null) {
                throw new InvalidArgumentException('Flexible slice plans require max_slices and min_amount_minor without predefined rows.');
            }

            if ($this->max_slices < 1 || $this->min_amount_minor < 1 || $this->min_amount_minor > $this->total_minor) {
                throw new InvalidArgumentException('Flexible slice plan capacity must fit within the plan total.');
            }

            return;
        }

        if ($this->slices->count() < 2) {
            throw new InvalidArgumentException('Equal and scheduled slice plans require at least two rows.');
        }

        if ($this->max_slices !== null || $this->min_amount_minor !== null) {
            throw new InvalidArgumentException('Predefined slice plans cannot include flexible capacity fields.');
        }

        $sliceTotal = $this->slices->toCollection()->sum(
            fn (VoucherSliceData $slice): int => $slice->amount_minor,
        );

        if ($sliceTotal !== $this->total_minor) {
            throw new InvalidArgumentException('Voucher slice amounts must exactly equal the plan total.');
        }

        if ($this->mode === VoucherSlicePlanMode::Equal) {
            $amounts = $this->slices->toCollection()
                ->map(fn (VoucherSliceData $slice): int => $slice->amount_minor);

            if (($amounts->max() - $amounts->min()) > 1) {
                throw new InvalidArgumentException('Equal slice plan rows may differ by at most one minor unit.');
            }

            if ($this->selection !== VoucherSliceSelectionPolicy::NextOnly) {
                throw new InvalidArgumentException('Equal slice plans require the next_only selection policy.');
            }

            return;
        }

        if (! in_array($this->selection, [VoucherSliceSelectionPolicy::One, VoucherSliceSelectionPolicy::OneOrMany], true)) {
            throw new InvalidArgumentException('Scheduled slice plans require one or one_or_many selection.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function canonicalArray(): array
    {
        $slices = $this->slices->toCollection()
            ->sortBy(fn (VoucherSliceData $slice): int => $slice->sequence)
            ->values()
            ->map(fn (VoucherSliceData $slice): array => [
                'id' => $slice->id,
                'label' => $slice->label,
                'amount_minor' => $slice->amount_minor,
                'sequence' => $slice->sequence,
                'claim_on' => $slice->claim_on,
                'claim_by' => $slice->claim_by,
            ])
            ->all();

        return [
            'schema' => $this->schema,
            'mode' => $this->mode->value,
            'selection' => $this->selection->value,
            'total_minor' => $this->total_minor,
            'currency' => $this->currency,
            'slices' => $slices,
            'max_slices' => $this->max_slices,
            'min_amount_minor' => $this->min_amount_minor,
        ];
    }

    /**
     * @throws JsonException
     */
    public function hash(): string
    {
        return hash('sha256', json_encode(
            $this->canonicalArray(),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        ));
    }
}
