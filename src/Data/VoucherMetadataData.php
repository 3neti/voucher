<?php

namespace LBHurtado\Voucher\Data;

use Spatie\LaravelData\Data;

/**
 * Voucher Metadata Data
 *
 * Contains voucher issuance metadata for "x-ray" inspection before redemption.
 * All fields are nullable for backward compatibility.
 */
class VoucherMetadataData extends Data
{
    public function __construct(
        // Version & System Information
        public ?string $version = null,          // Voucher schema version (e.g., '1.0.0')
        public ?string $system_name = null,      // System name (from config)
        public ?string $copyright = null,        // Copyright holder (from env/config)
        public ?array $licenses = null,          // Array of licenses/registrations

        // Issuer information
        public ?string $issuer_id = null,        // User ID who created
        public ?string $issuer_name = null,      // User name
        public ?string $issuer_email = null,     // User email

        // Source/campaign information
        public ?string $campaign_id = null,      // Campaign ID if from campaign
        public ?string $campaign_name = null,    // Campaign display name
        public ?string $source = null,           // 'campaign', 'manual', 'api', 'bulk'

        // Redemption context
        public ?array $redemption_urls = null,   // Array of available endpoint URLs
        public ?string $primary_url = null,      // Main redemption URL (route('redeem'))

        // Security & Verification (optional)
        public ?string $public_key = null,       // Public key for verification
        public ?string $signature = null,        // Digital signature

        // Timestamps (ISO 8601)
        public ?string $created_at = null,       // When generated
        public ?string $issued_at = null,        // When issued (if different)

        // Additional context
        public ?string $notes = null,            // Admin notes
        public ?array $tags = null,              // Categorization tags
        public ?array $custom = null,            // Extensibility

        // Preview/X-ray controls (optional, backward compatible)
        public ?bool $preview_enabled = null,    // null => default allow
        public ?string $preview_scope = null,    // 'full' | 'requirements_only' | 'none'
        public ?string $preview_message = null,  // optional issuer note
    ) {}

    public static function rules(): array
    {
        return [
            'version' => ['nullable', 'string', 'max:20'],
            'system_name' => ['nullable', 'string', 'max:100'],
            'copyright' => ['nullable', 'string', 'max:255'],
            'licenses' => ['nullable', 'array'],
            'issuer_id' => ['nullable', 'string', 'max:255'],
            'issuer_name' => ['nullable', 'string', 'max:255'],
            'issuer_email' => ['nullable', 'email', 'max:255'],
            'campaign_id' => ['nullable', 'string', 'max:255'],
            'campaign_name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', 'string', 'in:campaign,manual,api,bulk'],
            'redemption_urls' => ['nullable', 'array'],
            'primary_url' => ['nullable', 'url', 'max:500'],
            'public_key' => ['nullable', 'string'],
            'signature' => ['nullable', 'string'],
            'created_at' => ['nullable', 'string'],
            'issued_at' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'tags' => ['nullable', 'array'],
            'custom' => ['nullable', 'array'],

            // Preview policy
            'preview_enabled' => ['nullable', 'boolean'],
            'preview_scope' => ['nullable', 'in:full,requirements_only,none'],
            'preview_message' => ['nullable', 'string'],
        ];
    }

    /**
     * Get redemption URL by type
     */
    public function getRedemptionUrl(string $type, ?string $code = null): ?string
    {
        $url = $this->redemption_urls[$type] ?? null;

        if ($url && $code) {
            return $url.'?code='.urlencode($code);
        }

        return $url;
    }

    /**
     * Check if tag exists
     */
    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->tags ?? [], true);
    }

    /**
     * Get specific license info
     */
    public function getLicense(string $type): ?string
    {
        return $this->licenses[$type] ?? null;
    }

    /**
     * Get active (non-null) licenses
     */
    public function getActiveLicenses(): array
    {
        return array_filter($this->licenses ?? []);
    }

    /**
     * Verify signature with public key (if present)
     *
     * @param  string  $data  Data to verify
     * @return bool True if signature is valid, false otherwise
     */
    public function verify(string $data): bool
    {
        if (! $this->public_key || ! $this->signature) {
            return false;
        }

        try {
            $publicKey = openssl_pkey_get_public($this->public_key);
            if ($publicKey === false) {
                return false;
            }

            $result = openssl_verify(
                $data,
                base64_decode($this->signature),
                $publicKey,
                OPENSSL_ALGO_SHA256
            );

            return $result === 1;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
