<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

/**
 * External system metadata for voucher tracking
 *
 * Provides flexible structure for external systems (games, loyalty programs, etc.)
 * to attach their own metadata to vouchers.
 *
 * @property ?string $external_id - External system's unique identifier
 * @property ?string $external_type - Type/category of external entity
 * @property ?string $reference_id - Reference to external record
 * @property ?string $user_id - External user/player/member ID
 * @property ?array $custom - Additional custom fields
 */
class ExternalMetadataData extends Data
{
    public function __construct(
        public ?string $external_id = null,
        public ?string $external_type = null,
        public ?string $reference_id = null,
        public ?string $user_id = null,
        public ?array $custom = null,
    ) {}

    public static function rules(): array
    {
        return [
            'external_id' => ['nullable', 'string', 'max:255'],
            'external_type' => ['nullable', 'string', 'max:100'],
            'reference_id' => ['nullable', 'string', 'max:255'],
            'user_id' => ['nullable', 'string', 'max:255'],
            'custom' => ['nullable', 'array'],
        ];
    }

    /**
     * Get a custom field value
     */
    public function getCustom(string $key, mixed $default = null): mixed
    {
        return $this->custom[$key] ?? $default;
    }

    /**
     * Check if custom field exists
     */
    public function hasCustom(string $key): bool
    {
        return isset($this->custom[$key]);
    }

    /**
     * Set a custom field value
     * Returns new instance (immutable)
     */
    public function withCustom(string $key, mixed $value): self
    {
        $custom = $this->custom ?? [];
        $custom[$key] = $value;

        return new self(
            external_id: $this->external_id,
            external_type: $this->external_type,
            reference_id: $this->reference_id,
            user_id: $this->user_id,
            custom: $custom,
        );
    }
}
