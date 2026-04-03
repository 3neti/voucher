<?php

namespace LBHurtado\Voucher\Traits;

/**
 * Trait HasExternalMetadata
 *
 * Provides external metadata functionality for Voucher model.
 * Allows storing completely freeform JSON metadata for external tracking.
 *
 * Use cases:
 * - Reference codes (invoicing/billing)
 * - Project tracking
 * - Custom identifiers
 * - Any LLM-processable data
 *
 * @property array $metadata
 *
 * @deprecated Since the settlement envelope migration. For payable/settlement vouchers,
 *             use the envelope's payload instead of this trait's external_metadata.
 *             The envelope provides richer features: audit trail, documents, signals, gates.
 *             This trait is preserved for backward compatibility with legacy vouchers.
 *             New payable/settlement vouchers automatically create envelopes.
 *             Run `php artisan vouchers:migrate-to-envelopes` to migrate existing vouchers.
 * @see \LBHurtado\SettlementEnvelope\Models\SettlementEnvelope::$payload
 */
trait HasExternalMetadata
{
    /**
     * Get external metadata as array (freeform)
     *
     * Returns the raw metadata array for maximum flexibility.
     * No schema constraints - perfect for LLM processing.
     */
    public function getExternalMetadataAttribute(): ?array
    {
        return $this->metadata['external'] ?? null;
    }

    /**
     * Set external metadata from array
     *
     * @param  array|null  $value  Freeform JSON data
     */
    public function setExternalMetadataAttribute(?array $value): void
    {
        if ($value === null) {
            $metadata = $this->metadata ?? [];
            unset($metadata['external']);
            $this->metadata = $metadata;

            return;
        }

        $metadata = $this->metadata ?? [];
        $metadata['external'] = $value;
        $this->metadata = $metadata;
    }

    /**
     * Query scope: Filter by external metadata field
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $field  Field name within external metadata
     * @param  mixed  $value  Value to match
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereExternal($query, string $field, mixed $value)
    {
        return $query->whereJsonContains("metadata->external->{$field}", $value);
    }

    /**
     * Query scope: Filter by multiple external metadata values
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $field  Field name within external metadata
     * @param  array  $values  Values to match
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWhereExternalIn($query, string $field, array $values)
    {
        return $query->where(function ($q) use ($field, $values) {
            foreach ($values as $value) {
                $q->orWhereJsonContains("metadata->external->{$field}", $value);
            }
        });
    }
}
