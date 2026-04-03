<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use LBHurtado\Voucher\Enums\VoucherInputField;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;

/**
 * @property array<VoucherInputField> $fields
 */
class InputFieldsData extends Data
{
    use HasSafeDefaults;

    /**
     * @param  array<VoucherInputField>  $fields
     */
    public function __construct(
        #[WithCast(EnumCast::class, VoucherInputField::class)]
        public ?array $fields = []
    ) {
        $this->applyRulesAndDefaults();
        //        $this->fields = empty($fields) ? config('instructions.input_fields') : $fields;
    }

    public static function fromArray(array $input): self
    {
        // ✅ Ensure 'fields' exists and is an array
        $fields = isset($input['fields']) && is_array($input['fields'])
            ? $input['fields']
            : [];

        // 🧼 Normalize to VoucherInputField enums
        $fields = array_map(
            fn ($field) => VoucherInputField::from($field),
            $fields
        );

        return new self($fields);
    }

    //    public static function fromArray(array $input): self
    //    {
    //        $fields = $input['fields'] ?? $input;
    //
    //        return new self(array_map(
    //            fn ($field) => VoucherInputField::from($field),
    //            $fields
    //        ));
    //    }

    public function contains(VoucherInputField $field): bool
    {
        return in_array($field, $this->fields, true);
    }

    public function toArray(): array
    {
        return array_map(fn ($field) => $field->value, $this->fields);
    }

    public static function rules(): array
    {
        return [
            'fields' => ['required', 'array'],
            'fields.*' => ['string', 'in:'.implode(',', array_column(VoucherInputField::cases(), 'value'))],
        ];
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'fields' => [
                // reuse the same rules as above
                static::rules()['fields'],
                // default comes from config, e.g. ['email','mobile','reference_code']
                config('instructions.input_fields'),
            ],
        ];
    }

    //    public static function from(...$payloads): static
    //    {
    //        /** Ensure 'fields' is always an array */
    //        if (!isset($payloads[0]['fields']) || !is_array($payloads[0]['fields'])) {
    //            $payloads[0]['fields'] = [];
    //        }
    //
    //        // Normalize each field into a VoucherInputField enum instance
    //        $payloads[0]['fields'] = array_map(
    //            fn ($field) => VoucherInputField::from($field),
    //            $payloads[0]['fields']
    //        );
    //
    //        return parent::from(...$payloads);
    //    }
}
