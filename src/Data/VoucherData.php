<?php

namespace LBHurtado\Voucher\Data;

use Illuminate\Support\Carbon;
use LBHurtado\Cash\Data\CashData;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Contact\Data\ContactData;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\ModelInput\Data\InputData;
use LBHurtado\Voucher\Models\Voucher as VoucherModel;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class VoucherData extends Data
{
    public function __construct(
        public string $code,
        public ?ModelData $owner,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $created_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $starts_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $expires_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $redeemed_at,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class, timeZone: 'Asia/Manila')]
        public ?Carbon $processed_on,
        public bool $processed,
        public ?VoucherInstructionsData $instructions,
        /** @var InputData[] */
        public DataCollection $inputs,
        public ?CashData $cash = null,
        public ?ContactData $contact = null,
        public ?DisbursementData $disbursement = null,
        //        public ?ModelData                   $redeemer,
        // Computed fields
        public ?string $status = null,
        public ?float $amount = null,
        public ?string $currency = null,
        public ?bool $is_expired = null,
        public ?bool $is_redeemed = null,
        public ?bool $can_redeem = null,
        public ?array $external_metadata = null,
        public ?float $target_amount = null,
        public ?string $voucher_type = null,
        // Slice / Divisible fields
        public ?string $slice_mode = null,
        public ?int $max_slices = null,
        public ?float $slice_amount = null,
        public ?float $min_withdrawal = null,
        public int $consumed_slices = 0,
        public int $remaining_slices = 0,
        public float $remaining_balance = 0.0,
        public bool $can_withdraw = false,
        /** @var DisbursementData[] */
        public array $disbursements = [],
    ) {}

    public static function fromModel(VoucherModel $model): static
    {
        $instructions = null;
        try {
            $instructions = $model->instructions instanceof VoucherInstructionsData
                ? $model->instructions
                : ($model->instructions
                    ? VoucherInstructionsData::from($model->instructions)
                    : null
                );
        } catch (\Exception $e) {
            // Instructions might not exist or be invalid
        }

        return new static(
            code: $model->code,
            owner: $model->owner
                ? ModelData::fromModel($model->owner)
                : null,
            created_at: $model->created_at,
            starts_at: $model->starts_at,
            expires_at: $model->expires_at,
            redeemed_at: $model->redeemed_at,
            processed_on: $model->processed_on,
            processed: $model->processed,
            instructions: $instructions,
            inputs: new DataCollection(InputData::class, $model->inputs),
            cash: $model->cash instanceof Cash ? CashData::fromModel($model->cash) : null,
            contact: $model->contact instanceof Contact ? ContactData::fromModel($model->contact) : null,
            disbursement: DisbursementData::fromMetadata($model->metadata),
            //            redeemer: $model->redeemer
            //                ? ModelData::fromModel($model->redeemer)
            //                : null,
            // Computed fields
            status: static::computeStatus($model),
            amount: $instructions?->cash?->amount,
            currency: $instructions?->cash?->currency ?? 'PHP',
            is_expired: $model->isExpired(),
            is_redeemed: $model->isRedeemed(),
            can_redeem: static::computeCanRedeem($model),
            external_metadata: $model->external_metadata,
            target_amount: $model->target_amount,
            voucher_type: $model->voucher_type?->value,
            // Slice / Divisible fields
            slice_mode: $model->getSliceMode(),
            max_slices: $model->getMaxSlices(),
            slice_amount: $model->getSliceAmount(),
            min_withdrawal: $model->getMinWithdrawal(),
            consumed_slices: $model->getConsumedSlices(),
            remaining_slices: $model->getRemainingSlices(),
            remaining_balance: $model->getRemainingBalance(),
            can_withdraw: $model->canWithdraw(),
            disbursements: DisbursementData::allFromMetadata($model->metadata),
        );
    }

    /**
     * Compute voucher status.
     */
    protected static function computeStatus(VoucherModel $model): string
    {
        return $model->display_status;
    }

    /**
     * Check if voucher can be redeemed.
     */
    protected static function computeCanRedeem(VoucherModel $model): bool
    {
        return ! $model->isRedeemed()
            && ! $model->isExpired()
            && (! $model->starts_at || $model->starts_at->isPast());
    }
}

class ModelData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public ?string $mobile
    ) {}

    public static function fromModel($model): static
    {
        return new static(
            id: $model->id,
            name: $model->name,
            email: $model->email,
            mobile: $model->mobile ?? null,
        );
    }
}
