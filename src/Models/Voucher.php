<?php

namespace LBHurtado\Voucher\Models;

use FrittenKeeZ\Vouchers\Models\Redeemer;
use FrittenKeeZ\Vouchers\Models\Voucher as BaseVoucher;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;
use LBHurtado\Cash\Models\Cash;
use LBHurtado\Contact\Models\Contact;
use LBHurtado\ModelInput\Contracts\InputInterface;
use LBHurtado\ModelInput\Traits\HasInputs;
use LBHurtado\SettlementEnvelope\Traits\HasEnvelopes;
use LBHurtado\Voucher\Data\VoucherData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Enums\VoucherState;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Observers\VoucherObserver;
use LBHurtado\Voucher\Traits\HasExternalMetadata;
use LBHurtado\Voucher\Traits\HasValidationResults;
use LBHurtado\Voucher\Traits\HasVoucherTiming;
use Spatie\LaravelData\WithData;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * Class Voucher.
 *
 * @property int $id
 * @property string $code
 * @property \Illuminate\Database\Eloquent\Model $owner
 * @property array $metadata
 * @property Carbon $starts_at
 * @property Carbon $expires_at
 * @property Carbon $redeemed_at
 * @property Carbon $processed_on
 * @property bool $processed
 * @property VoucherInstructionsData $instructions
 * @property \FrittenKeeZ\Vouchers\Models\Redeemer $redeemer
 * @property \Illuminate\Database\Eloquent\Collection $voucherEntities
 * @property \Illuminate\Database\Eloquent\Collection $redeemers
 * @property Cash $cash
 * @property Contact $contact
 * @property \LBHurtado\Voucher\Data\ExternalMetadataData $external_metadata
 * @property \LBHurtado\Voucher\Data\VoucherTimingData $timing
 * @property \LBHurtado\Voucher\Data\ValidationResultsData $validation_results
 *
 * @method int getKey()
 */
#[ObservedBy([VoucherObserver::class])]
class Voucher extends BaseVoucher implements HasMedia, InputInterface
{
    use HasEnvelopes;
    use HasExternalMetadata;
    use HasInputs;
    use HasValidationResults;
    use HasVoucherTiming;
    use InteractsWithMedia;
    use WithData;

    protected string $dataClass = VoucherData::class;

    /**
     * The attributes that are mass assignable.
     *
     * Extends parent's fillable array to include state management fields.
     */
    protected $fillable = [
        'code',
        'metadata',
        'starts_at',
        'expires_at',
        'redeemed_at',
        'state',  // Allow state to be mass-assigned
        'locked_at',
        'closed_at',
    ];

    public ?Redeemer $redeemer = null;

    protected function casts(): array
    {
        // Include parent's casts and add/override
        return array_merge(parent::casts(), [
            'processed_on' => 'datetime:Y-m-d H:i:s',
            'voucher_type' => VoucherType::class,
            'state' => VoucherState::class,
            'rules' => 'array',
            'locked_at' => 'datetime',
            'closed_at' => 'datetime',
        ]);
    }

    public function getRouteKeyName()
    {
        return 'code';
    }

    /**
     * Register media collections for voucher attachments.
     *
     * @deprecated The 'voucher_attachments' collection is deprecated for payable/settlement vouchers.
     *             Use the settlement envelope's documents instead, which provide:
     *             - Document type classification (REFERENCE_DOC, etc.)
     *             - Review status tracking (pending, accepted, rejected)
     *             - Audit trail for all changes
     *             New payable/settlement vouchers automatically create envelopes.
     *             Run `php artisan vouchers:migrate-to-envelopes` to migrate existing vouchers.
     * @see \LBHurtado\SettlementEnvelope\Models\SettlementEnvelope::attachments()
     */
    public function registerMediaCollections(): void
    {
        $disk = config('voucher.attachments.disk', 'public');

        // @deprecated For payable/settlement vouchers, use envelope documents instead
        $this->addMediaCollection('voucher_attachments')
            ->useDisk($disk)
            ->acceptsFile(function ($file) {
                return true; // Accept all file types configured in validation
            });

        $this->addMediaCollection('voucher_invoice')
            ->singleFile()
            ->useDisk($disk);
    }

    /**
     * Override the default to trim your incoming code.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $column = $field ?? $this->getRouteKeyName();

        return $this
            ->where($column, strtoupper(trim($value)))
            ->firstOrFail();
    }

    public function setProcessedAttribute(bool $value): self
    {
        $this->setAttribute('processed_on', $value ? now() : null);

        return $this;
    }

    public function getProcessedAttribute(): bool
    {
        return $this->getAttribute('processed_on')
            && $this->getAttribute('processed_on') <= now();
    }

    public function getInstructionsAttribute(): VoucherInstructionsData
    {
        return VoucherInstructionsData::from($this->metadata['instructions']);
    }

    public function getCashAttribute(): ?Cash
    {
        return $this->getEntities(Cash::class)->first();
    }

    public function getRedeemerAttribute(): ?Redeemer
    {
        return $this->redeemers->first();
    }

    public function getContactAttribute(): ?Contact
    {
        return $this->redeemers?->first()?->redeemer;
    }

    /**
     * Target amount accessor - converts between minor units (storage) and major units (display)
     */
    protected function targetAmount(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => $value ? $value / 100 : null, // Convert centavos to pesos
            set: fn ($value) => $value ? $value * 100 : null  // Convert pesos to centavos
        );
    }

    // Domain Guards

    public function canAcceptPayment(): bool
    {
        return in_array($this->voucher_type, [VoucherType::PAYABLE, VoucherType::SETTLEMENT])
            && $this->state === VoucherState::ACTIVE
            && ! $this->isExpired()
            && ! $this->isClosed();
    }

    public function canRedeem(): bool
    {
        return in_array($this->voucher_type, [VoucherType::REDEEMABLE, VoucherType::SETTLEMENT])
            && $this->state === VoucherState::ACTIVE
            && ! $this->isExpired()
            && $this->redeemed_at === null;
    }

    public function isLocked(): bool
    {
        return $this->state === VoucherState::LOCKED;
    }

    public function isClosed(): bool
    {
        return $this->state === VoucherState::CLOSED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Computed display status — single source of truth.
     *
     * Priority: terminal states > redeemed > expired > pending > active.
     */
    public function getDisplayStatusAttribute(): string
    {
        if ($this->state === VoucherState::CANCELLED) {
            return 'cancelled';
        }

        if ($this->state === VoucherState::CLOSED) {
            return 'closed';
        }

        if ($this->state === VoucherState::LOCKED) {
            return 'locked';
        }

        if ($this->isRedeemed()) {
            return 'redeemed';
        }

        if ($this->isExpired()) {
            return 'expired';
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return 'pending';
        }

        return 'active';
    }

    // Computed Amount Methods (derived from wallet ledger)

    public function getPaidTotal(): float
    {
        if (! $this->cash || ! $this->cash->wallet) {
            return 0.0;
        }

        return $this->cash->wallet->transactions()
            ->where('type', 'deposit')
            ->whereJsonContains('meta->flow', 'pay')
            ->where('confirmed', true)  // Only count confirmed payments
            ->sum('amount') / 100; // Convert minor units to major
    }

    public function getRedeemedTotal(): float
    {
        if (! $this->cash || ! $this->cash->wallet) {
            return 0.0;
        }

        return abs($this->cash->wallet->transactions()
            ->where('type', 'withdraw')
            ->whereJsonContains('meta->flow', 'redeem')
            ->sum('amount')) / 100; // Convert minor units to major (withdrawals are negative)
    }

    public function getRemaining(): float
    {
        if (! $this->target_amount) {
            return 0.0;
        }

        return $this->target_amount - $this->getPaidTotal();
    }

    // Slice / Divisible Voucher Methods

    public function getSliceMode(): ?string
    {
        try {
            return $this->instructions->cash->slice_mode;
        } catch (\Throwable) {
            return null;
        }
    }

    public function isDivisible(): bool
    {
        return $this->getSliceMode() !== null;
    }

    public function getSliceAmount(): ?float
    {
        if ($this->getSliceMode() !== 'fixed') {
            return null;
        }

        try {
            return $this->instructions->cash->amount / $this->instructions->cash->slices;
        } catch (\Throwable) {
            return null;
        }
    }

    public function getMaxSlices(): ?int
    {
        $mode = $this->getSliceMode();
        if (! $mode) {
            return null;
        }

        try {
            return match ($mode) {
                'fixed' => $this->instructions->cash->slices,
                'open' => $this->instructions->cash->max_slices,
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    public function getMinWithdrawal(): ?float
    {
        $mode = $this->getSliceMode();
        if (! $mode) {
            return null;
        }

        try {
            return match ($mode) {
                'fixed' => $this->getSliceAmount(),
                'open' => $this->instructions->cash->min_withdrawal ?? config('voucher.min_withdrawal', 100),
                default => null,
            };
        } catch (\Throwable) {
            return null;
        }
    }

    public function getConsumedSlices(): int
    {
        if (! $this->cash || ! $this->cash->wallet) {
            return 0;
        }

        return (int) $this->cash->wallet->transactions()
            ->where('type', 'withdraw')
            ->whereJsonContains('meta->flow', 'redeem')
            ->where('confirmed', true)
            ->count();
    }

    public function getRemainingSlices(): int
    {
        $max = $this->getMaxSlices();

        return $max ? max(0, $max - $this->getConsumedSlices()) : 0;
    }

    public function getRemainingBalance(): float
    {
        if (! $this->cash || ! $this->cash->wallet) {
            return 0.0;
        }

        return $this->cash->wallet->balance / 100;
    }

    public function hasRemainingSlices(): bool
    {
        return $this->getRemainingSlices() > 0
            && $this->getRemainingBalance() >= ($this->getMinWithdrawal() ?? 0);
    }

    public function canWithdraw(): bool
    {
        return $this->isRedeemed()
            && $this->isDivisible()
            && $this->hasRemainingSlices()
            && ! $this->isExpired()
            && $this->state === VoucherState::ACTIVE;
    }
}
