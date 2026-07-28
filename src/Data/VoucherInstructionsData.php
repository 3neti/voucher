<?php

namespace LBHurtado\Voucher\Data;

use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Data\Casts\CarbonIntervalCast;
use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use LBHurtado\Voucher\Data\Transformers\TtlToStringTransformer;
use LBHurtado\Voucher\Enums\RiderContentFormat;
use LBHurtado\Voucher\Enums\RiderStampArtworkSource;
use LBHurtado\Voucher\Enums\RiderStampArtworkTreatment;
use LBHurtado\Voucher\Enums\RiderStampClaimMarker;
use LBHurtado\Voucher\Enums\RiderStampClaimMarkerPosition;
use LBHurtado\Voucher\Enums\RiderStampCopySource;
use LBHurtado\Voucher\Enums\RiderStampFit;
use LBHurtado\Voucher\Enums\RiderStampPosition;
use LBHurtado\Voucher\Enums\RiderStampSource;
use LBHurtado\Voucher\Enums\RiderStampTheme;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Rules\ValidISODuration;
use Propaganistas\LaravelPhone\Rules\Phone;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Transformers\DateTimeInterfaceTransformer;

class VoucherInstructionsData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public CashInstructionData $cash,
        public InputFieldsData $inputs,
        public FeedbackInstructionData $feedback,
        public RiderInstructionData $rider,
        public ?int $count,            // Number of vouchers to generate
        public ?string $prefix,           // Prefix for voucher codes
        public ?string $mask,             // Mask for voucher codes
        #[WithTransformer(TtlToStringTransformer::class)]
        #[WithCast(CarbonIntervalCast::class)]
        public ?CarbonInterval $ttl,              // Expiry time (TTL)
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $starts_at = null,
        #[WithTransformer(DateTimeInterfaceTransformer::class)]
        #[WithCast(DateTimeInterfaceCast::class)]
        public ?Carbon $expires_at = null,
        public ?ValidationInstructionData $validation = null, // Validation instructions
        public ?VoucherMetadataData $metadata = null,   // System metadata (version, copyright, licenses, issuer, redemption URLs)
        public ?VoucherType $voucher_type = null, // Settlement voucher type (REDEEMABLE, PAYABLE, SETTLEMENT)
        public ?float $target_amount = null, // Target amount for PAYABLE/SETTLEMENT vouchers
        public ?array $rules = null,         // Settlement-specific rules (min_payment, max_payment, allow_overpayment, etc.)
        public ?ExecutionInstructionData $execution = null,
        public ?ClaimInstructionData $claim = null,
    ) {
        $this->applyRulesAndDefaults();
        //        $this->ttl = $ttl ?: CarbonInterval::hours(config('instructions.ttl'));
    }

    public static function rules(): array
    {
        return [
            'cash.amount' => 'required|numeric|min:0',
            'cash.currency' => 'required|string|size:3',

            'cash.validation.secret' => 'nullable|string',
            'cash.validation.mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'cash.validation.payable' => 'nullable|string',
            'cash.validation.country' => 'nullable|string|size:2',
            'cash.validation.location' => 'nullable|string',
            'cash.validation.radius' => 'nullable|string',
            'cash.validation.mobile_verification' => 'nullable',
            'cash.settlement_rail' => 'nullable|string|in:INSTAPAY,PESONET',
            'cash.fee_strategy' => 'nullable|string|in:absorb,include,add',
            'cash.slice_mode' => 'nullable|string|in:fixed,open',
            'cash.slices' => 'nullable|integer|min:1',
            'cash.max_slices' => 'nullable|integer|min:1',
            'cash.min_withdrawal' => 'nullable|numeric|min:0',

            'inputs' => ['nullable', 'array'],
            'inputs.fields' => ['nullable', 'array'],
            'inputs.fields.*' => ['nullable', 'string', 'in:'.implode(',', VoucherInputField::values())],
            'feedback.mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'feedback.email' => 'nullable|email',
            'feedback.webhook' => 'nullable|url',

            'rider.message' => 'nullable|string|min:1',
            'rider.url' => 'nullable|url',
            'rider.redirect_timeout' => 'nullable|integer|min:0|max:300',
            'rider.splash' => 'nullable|string|max:51200',
            'rider.splash_timeout' => 'nullable|integer|min:0|max:60',
            'rider.splash_meta' => 'nullable|array',
            'rider.splash_meta.sanitized' => 'nullable|boolean',
            'rider.splash_meta.html_profile' => 'nullable|string',
            'rider.og_source' => 'nullable|string|in:message,url,splash',
            'rider.message_format' => 'nullable|string|in:'.implode(',', array_column(RiderContentFormat::cases(), 'value')),
            'rider.splash_format' => 'nullable|string|in:'.implode(',', array_column(RiderContentFormat::cases(), 'value')),
            'rider.stamp' => 'nullable|array',
            'rider.stamp.source' => 'nullable|string|in:'.implode(',', array_column(RiderStampSource::cases(), 'value')),
            'rider.stamp.title' => 'nullable|string|max:120',
            'rider.stamp.description' => 'nullable|string|max:240',
            'rider.stamp.fit' => 'nullable|string|in:'.implode(',', array_column(RiderStampFit::cases(), 'value')),
            'rider.stamp.position' => 'nullable|string|in:'.implode(',', array_column(RiderStampPosition::cases(), 'value')),
            'rider.stamp.scrim' => 'nullable|integer|min:0|max:100',
            'rider.stamp.theme' => 'nullable|string|in:'.implode(',', array_column(RiderStampTheme::cases(), 'value')),
            'rider.stamp.version' => 'nullable|integer|in:'.implode(',', [
                RiderStampData::LEGACY_SCHEMA_VERSION,
                RiderStampData::SCHEMA_VERSION,
            ]),
            'rider.stamp.artwork_source' => 'nullable|string|in:'.implode(',', array_column(RiderStampArtworkSource::cases(), 'value')),
            'rider.stamp.artwork_treatment' => 'nullable|string|in:'.implode(',', array_column(RiderStampArtworkTreatment::cases(), 'value')),
            'rider.stamp.copy_source' => 'nullable|string|in:'.implode(',', array_column(RiderStampCopySource::cases(), 'value')),
            'rider.stamp.show_logo' => 'nullable|boolean',
            'rider.stamp.show_tagline' => 'nullable|boolean',
            'rider.stamp.claim_marker' => 'nullable|string|in:'.implode(',', array_column(RiderStampClaimMarker::cases(), 'value')),
            'rider.stamp.claim_marker_position' => 'nullable|string|in:'.implode(',', array_column(RiderStampClaimMarkerPosition::cases(), 'value')),

            'count' => 'required|integer|min:1',
            'prefix' => 'nullable|string|min:1',
            'mask' => [
                'nullable',
                'string',
                function ($attribute, $value, $fail) {
                    if (! preg_match("/^[\*\-]+$/", $value)) {
                        $fail('The :attribute may only contain asterisks (*) and hyphens (-).');
                    }

                    $asterisks = substr_count($value, '*');
                    $min = config('voucher.mask.min_asterisks', 4);
                    $max = config('voucher.mask.max_asterisks', 8);

                    if ($asterisks < $min) {
                        $fail("The :attribute must contain at least {$min} asterisks (*).");
                    }

                    if ($asterisks > $max) {
                        $fail("The :attribute must contain at most {$max} asterisks (*).");
                    }
                },
            ],
            'ttl' => ['nullable', new ValidISODuration],
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',

            // Settlement voucher fields
            'voucher_type' => 'nullable|string|in:redeemable,payable,settlement',
            'target_amount' => 'nullable|numeric|min:0|required_if:voucher_type,payable,settlement',
            'rules' => 'nullable|array',
            'rules.min_payment' => 'nullable|numeric|min:0',
            'rules.max_payment' => 'nullable|numeric|min:0',
            'rules.allow_overpayment' => 'nullable|boolean',
            'rules.auto_close_on_full_payment' => 'nullable|boolean',

            'execution' => ['nullable', 'array'],
            'execution.schema' => ['nullable', 'string', 'min:1'],
            'execution.driver' => ['nullable', 'string', 'min:1'],
            'execution.mode' => ['nullable', 'string', 'min:1'],
            'execution.pipeline' => ['nullable', 'array'],
            'execution.pipeline.*' => ['string', 'min:1'],
            'execution.fallback' => ['nullable', 'string', 'min:1'],
            'execution.visibility' => ['nullable', 'array'],
            'execution.visibility.*' => ['string', 'min:1'],
            'execution.metadata' => ['nullable', 'array'],

            'claim' => ['nullable', 'array'],
            'claim.outcomes' => ['required_with:claim', 'array', 'min:1'],
            'claim.outcomes.*' => ['required', 'array'],
            'claim.outcomes.*.key' => ['required', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'claim.outcomes.*.pricing_profile' => ['nullable', 'string', 'min:1'],
            'claim.outcomes.*.requirements' => ['nullable', 'array'],
            'claim.selection' => ['nullable', 'string', 'in:claimant,server'],
            'claim.consumption' => ['nullable', 'string', 'in:one_of'],
            'claim.default_outcome' => ['nullable', 'string', 'regex:/^[a-z][a-z0-9_]*$/'],
            'claim.onboarding' => ['nullable', 'array'],
            'claim.onboarding.mode' => ['nullable', 'string', 'in:never,if_required,required'],
            'claim.onboarding.profile' => ['nullable', 'string', 'min:1'],
            'claim.claimant' => ['nullable', 'array'],
            'claim.claimant.mode' => ['nullable', 'string', 'in:unbound,recipient'],
            'claim.claimant.reference' => [
                'nullable',
                'string',
                'min:1',
                'required_if:claim.claimant.mode,recipient',
            ],
            'claim.profile' => ['nullable', 'string', 'in:'.ClaimInstructionData::SCHEMA],

            'metadata' => ['nullable', 'array'],
            'metadata.flow_type' => ['nullable', 'string'],
            'metadata.issuer_id' => ['nullable', 'string'],
            'metadata.collection_wallet_id' => ['nullable'],

            // Validation instructions
            'validation' => 'nullable|array',
            'validation.location' => 'nullable|array',
            'validation.location.required' => 'required_with:validation.location|boolean',
            'validation.location.target_lat' => 'required_with:validation.location|numeric|between:-90,90',
            'validation.location.target_lng' => 'required_with:validation.location|numeric|between:-180,180',
            'validation.location.radius_meters' => 'required_with:validation.location|integer|min:1|max:10000',
            'validation.location.on_failure' => 'required_with:validation.location|in:block,warn',

            'validation.time' => 'nullable|array',
            'validation.time.window' => 'nullable|array',
            'validation.time.window.start_time' => 'required_with:validation.time.window|date_format:H:i',
            'validation.time.window.end_time' => 'required_with:validation.time.window|date_format:H:i',
            'validation.time.window.timezone' => 'required_with:validation.time.window|string|timezone',
            'validation.time.limit_minutes' => 'nullable|integer|min:1|max:1440',
            'validation.time.track_duration' => 'nullable|boolean',

            'validation.signature' => 'nullable|array',
            'validation.signature.required' => 'required_with:validation.signature|boolean',
            'validation.signature.on_failure' => 'nullable|in:block,warn',

            'validation.selfie' => 'nullable|array',
            'validation.selfie.required' => 'required_with:validation.selfie|boolean',
            'validation.selfie.on_failure' => 'nullable|in:block,warn',

            'validation.otp' => 'nullable|array',
            'validation.otp.required' => 'required_with:validation.otp|boolean',
            'validation.otp.on_failure' => 'nullable|in:block,warn',

            'validation.face_match' => 'nullable|array',
            'validation.face_match.required' => 'required_with:validation.face_match|boolean',
            'validation.face_match.on_failure' => 'nullable|in:block,warn',
            'validation.face_match.min_confidence' => 'nullable|numeric|min:0|max:1',
        ];
    }

    public static function createFromAttribs(array $attribs): VoucherInstructionsData
    {
        $validated = validator($attribs, static::rules())->validate();
        $data_array = [
            'cash' => [
                'amount' => $validated['cash']['amount'],
                'currency' => $validated['cash']['currency'],
                'settlement_rail' => $validated['cash']['settlement_rail'] ?? null,
                'fee_strategy' => $validated['cash']['fee_strategy'] ?? 'absorb',
                'slice_mode' => $validated['cash']['slice_mode'] ?? null,
                'slices' => $validated['cash']['slices'] ?? null,
                'max_slices' => $validated['cash']['max_slices'] ?? null,
                'min_withdrawal' => isset($validated['cash']['min_withdrawal'])
                    ? (float) $validated['cash']['min_withdrawal']
                    : null,
                'validation' => [
                    'secret' => $validated['cash']['validation']['secret'] ?? null,
                    'mobile' => $validated['cash']['validation']['mobile'] ?? null,
                    'payable' => $validated['cash']['validation']['payable'] ?? null,
                    'country' => $validated['cash']['validation']['country'] ?? null,
                    'location' => $validated['cash']['validation']['location'] ?? null,
                    'radius' => $validated['cash']['validation']['radius'] ?? null,
                    'mobile_verification' => $validated['cash']['validation']['mobile_verification'] ?? null,
                ],
            ],
            'inputs' => [
                'fields' => $validated['inputs']['fields'] ?? null,
            ],
            'feedback' => [
                'email' => $validated['feedback']['email'] ?? null,
                'mobile' => $validated['feedback']['mobile'] ?? null,
                'webhook' => $validated['feedback']['webhook'] ?? null,
            ],
            'rider' => [
                'message' => $validated['rider']['message'] ?? null,
                'url' => $validated['rider']['url'] ?? null,
                'redirect_timeout' => $validated['rider']['redirect_timeout'] ?? null,
                'splash' => $validated['rider']['splash'] ?? null,
                'splash_timeout' => $validated['rider']['splash_timeout'] ?? null,
                'splash_meta' => $validated['rider']['splash_meta'] ?? null,
                'og_source' => $validated['rider']['og_source'] ?? null,
                'message_format' => $validated['rider']['message_format'] ?? null,
                'splash_format' => $validated['rider']['splash_format'] ?? null,
                'stamp' => $validated['rider']['stamp'] ?? null,
            ],
            'validation' => isset($validated['validation']) ? [
                'signature' => isset($validated['validation']['signature']) ? [
                    'required' => $validated['validation']['signature']['required'],
                    'on_failure' => $validated['validation']['signature']['on_failure'] ?? 'block',
                ] : null,

                'selfie' => isset($validated['validation']['selfie']) ? [
                    'required' => $validated['validation']['selfie']['required'],
                    'on_failure' => $validated['validation']['selfie']['on_failure'] ?? 'block',
                ] : null,
                'location' => isset($validated['validation']['location']) ? [
                    'required' => $validated['validation']['location']['required'],
                    'target_lat' => $validated['validation']['location']['target_lat'],
                    'target_lng' => $validated['validation']['location']['target_lng'],
                    'radius_meters' => $validated['validation']['location']['radius_meters'],
                    'on_failure' => $validated['validation']['location']['on_failure'],
                ] : null,
                'otp' => isset($validated['validation']['otp']) ? [
                    'required' => $validated['validation']['otp']['required'],
                    'on_failure' => $validated['validation']['otp']['on_failure'] ?? 'block',
                ] : null,
                'time' => isset($validated['validation']['time']) ? [
                    'window' => isset($validated['validation']['time']['window']) ? [
                        'start_time' => $validated['validation']['time']['window']['start_time'],
                        'end_time' => $validated['validation']['time']['window']['end_time'],
                        'timezone' => $validated['validation']['time']['window']['timezone'],
                    ] : null,
                    'limit_minutes' => $validated['validation']['time']['limit_minutes'] ?? null,
                    'track_duration' => $validated['validation']['time']['track_duration'] ?? true,
                ] : null,
                'face_match' => isset($validated['validation']['face_match']) ? [
                    'required' => $validated['validation']['face_match']['required'],
                    'on_failure' => $validated['validation']['face_match']['on_failure'] ?? 'block',
                    'min_confidence' => isset($validated['validation']['face_match']['min_confidence'])
                        ? (float) $validated['validation']['face_match']['min_confidence']
                        : null,
                ] : null,
            ] : null,
            'count' => $validated['count'],
            'prefix' => $validated['prefix'] ?? '',
            'mask' => $validated['mask'] ?? '',
            'ttl' => $validated['ttl'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'metadata' => $validated['metadata'] ?? null,
            'voucher_type' => $validated['voucher_type'] ?? null,
            'target_amount' => $validated['target_amount'] ?? null,
            'rules' => $validated['rules'] ?? null,
            'execution' => isset($validated['execution']) ? [
                'schema' => $validated['execution']['schema'] ?? ExecutionInstructionData::SCHEMA,
                'driver' => $validated['execution']['driver'] ?? 'default',
                'mode' => $validated['execution']['mode'] ?? null,
                'pipeline' => $validated['execution']['pipeline'] ?? null,
                'fallback' => $validated['execution']['fallback'] ?? null,
                'visibility' => $validated['execution']['visibility'] ?? null,
                'metadata' => $validated['execution']['metadata'] ?? null,
            ] : null,
            'claim' => isset($validated['claim']) ? [
                'outcomes' => $validated['claim']['outcomes'],
                'selection' => $validated['claim']['selection'] ?? 'claimant',
                'consumption' => $validated['claim']['consumption'] ?? 'one_of',
                'default_outcome' => $validated['claim']['default_outcome'] ?? null,
                'onboarding' => $validated['claim']['onboarding'] ?? null,
                'claimant' => $validated['claim']['claimant'] ?? null,
                'profile' => $validated['claim']['profile'] ?? ClaimInstructionData::SCHEMA,
            ] : null,
        ];

        return VoucherInstructionsData::from($data_array);
    }

    public static function generateFromScratch(): VoucherInstructionsData
    {
        $data_array = [
            'cash' => [
                'amount' => 0,
                'currency' => Number::defaultCurrency(),
                'settlement_rail' => null,
                'fee_strategy' => 'absorb',
                'slice_mode' => null,
                'slices' => null,
                'max_slices' => null,
                'min_withdrawal' => null,
                'validation' => [
                    'secret' => null,
                    'mobile' => null,
                    'payable' => null,
                    'country' => config('instructions.cash.validation_rules.country'),
                    'location' => null,
                    'radius' => null,
                ],
            ],
            'inputs' => [
                'fields' => [],
            ],
            'feedback' => [
                'mobile' => null,
                'email' => null,
                'webhook' => null,
            ],
            'rider' => [
                'message' => null,
                'url' => null,
                'redirect_timeout' => null,
                'splash' => null,
                'splash_timeout' => null,
                'splash_meta' => null,
                'og_source' => null,
                'message_format' => null,
                'splash_format' => null,
                'stamp' => null,
            ],
            'validation' => [
                'signature' => null,
                'selfie' => null,
                'location' => null,
                'otp' => null,
                'time' => null,
                'face_match' => null,
            ],
            'count' => 1, // New field for count
            'prefix' => null, // New field for prefix
            'mask' => null, // New field for mask
            'ttl' => null, // New field for ttl
            'starts_at' => null,
            'expires_at' => null,
        ];

        return VoucherInstructionsData::from($data_array);
    }

    public function executionInstruction(): ExecutionInstructionData
    {
        return $this->execution ?? ExecutionInstructionData::from([]);
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'count' => [
                ['required', 'integer', 'min:1'],
                config('instructions.count', 1),
            ],
            'prefix' => [
                ['required', 'string', 'min:1', 'max:10'],
                config('instructions.prefix', ''),
            ],
            'mask' => [
                ['required', 'string', 'min:3', 'regex:/\*/'],
                config('instructions.mask'),
            ],
            //            'ttl' => [
            //                // nullable ISO-8601 duration format:
            //                ['required', 'string',
            //                    // this regex loosely matches e.g. P1DT2H30M or PT12H
            //                    'regex:/^P(?!$)(\d+Y)?(\d+M)?(\d+W)?(\d+D)?(T(\d+H)?(\d+M)?(\d+S)?)?$/'
            //                ],
            //                // default to 12 hours (or pull from config('instructions.ttl','PT12H'))
            //                CarbonInterval::hours(config('instructions.ttl', 12)),
            //            ],
        ];
    }

    // TODO: move to a helper class (e.g., ArrayCleaner::clean())
    public function toCleanArray(): array
    {
        $array = $this->toArray();

        return self::cleanArray($array);
    }

    protected static function cleanArray(array $array, string $parentKey = ''): array
    {
        return collect($array)
            ->map(function ($value, $key) use ($parentKey) {
                $currentKey = $parentKey ? "{$parentKey}.{$key}" : $key;

                if (is_array($value)) {
                    // Recursively clean nested arrays
                    $cleaned = self::cleanArray($value, $currentKey);

                    return $cleaned;
                }

                // Leave scalars intact if not empty
                return $value;
            })
            ->filter(function ($value, $key) use ($parentKey) {
                $currentKey = $parentKey ? "{$parentKey}.{$key}" : $key;

                // Preserve the original Rider wire shape while allowing additive fields
                // to remain absent from legacy payloads until they are configured.
                $legacyRiderFields = [
                    'rider.message',
                    'rider.url',
                    'rider.redirect_timeout',
                    'rider.splash',
                    'rider.splash_timeout',
                    'rider.splash_meta',
                    'rider.og_source',
                ];

                if ($currentKey === 'rider' || in_array($currentKey, $legacyRiderFields, true)) {
                    return true;
                }

                // Filter out only nulls and empty strings — keep empty arrays
                return ! (is_null($value) || (is_string($value) && trim($value) === ''));
            })
            ->all();
    }
}
