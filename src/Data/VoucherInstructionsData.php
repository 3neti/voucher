<?php

namespace LBHurtado\Voucher\Data;

use Carbon\CarbonInterval;
use Illuminate\Support\Number;
use LBHurtado\Voucher\Data\Casts\CarbonIntervalCast;
use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use LBHurtado\Voucher\Data\Transformers\TtlToStringTransformer;
use LBHurtado\Voucher\Enums\VoucherInputField;
use LBHurtado\Voucher\Enums\VoucherType;
use LBHurtado\Voucher\Rules\ValidISODuration;
use Propaganistas\LaravelPhone\Rules\Phone;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Attributes\WithTransformer;
use Spatie\LaravelData\Data;

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
        public ?ValidationInstructionData $validation = null, // Validation instructions
        public ?VoucherMetadataData $metadata = null,   // System metadata (version, copyright, licenses, issuer, redemption URLs)
        public ?VoucherType $voucher_type = null, // Settlement voucher type (REDEEMABLE, PAYABLE, SETTLEMENT)
        public ?float $target_amount = null, // Target amount for PAYABLE/SETTLEMENT vouchers
        public ?array $rules = null,         // Settlement-specific rules (min_payment, max_payment, allow_overpayment, etc.)
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
            'rider.og_source' => 'nullable|string|in:message,url,splash',

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
                'og_source' => $validated['rider']['og_source'] ?? null,
            ],
            'validation' => isset($validated['validation']) ? [
                'location' => isset($validated['validation']['location']) ? [
                    'required' => $validated['validation']['location']['required'],
                    'target_lat' => $validated['validation']['location']['target_lat'],
                    'target_lng' => $validated['validation']['location']['target_lng'],
                    'radius_meters' => $validated['validation']['location']['radius_meters'],
                    'on_failure' => $validated['validation']['location']['on_failure'],
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
            ] : null,
            'count' => $validated['count'],
            'prefix' => $validated['prefix'] ?? '',
            'mask' => $validated['mask'] ?? '',
            'ttl' => $validated['ttl'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'expires_at' => $validated['expires_at'] ?? null,
            'voucher_type' => isset($validated['voucher_type']) ? VoucherType::from($validated['voucher_type']) : null,
            'target_amount' => $validated['target_amount'] ?? null,
            'rules' => $validated['rules'] ?? null,
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
                'og_source' => null,
            ],
            'count' => 1, // New field for count
            'prefix' => null, // New field for prefix
            'mask' => null, // New field for mask
            'ttl' => null, // New field for ttl
        ];

        return VoucherInstructionsData::from($data_array);
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

                // NEVER filter out rider fields - keep all of them even if null
                if ($parentKey === 'rider' || $currentKey === 'rider') {
                    return true;
                }

                // Filter out only nulls and empty strings — keep empty arrays
                return ! (is_null($value) || (is_string($value) && trim($value) === ''));
            })
            ->all();
    }
}
