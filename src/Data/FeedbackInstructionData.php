<?php

namespace LBHurtado\Voucher\Data;

use LBHurtado\Voucher\Data\Traits\HasSafeDefaults;
use Propaganistas\LaravelPhone\Rules\Phone;
use Spatie\LaravelData\Data;

class FeedbackInstructionData extends Data
{
    use HasSafeDefaults;

    public function __construct(
        public ?string $email = null,
        public ?string $mobile = null,
        public ?string $webhook = null,
    ) {
        $this->applyRulesAndDefaults();
    }

    protected function rulesAndDefaults(): array
    {
        return [
            'email' => [
                ['required', 'email'],
                config('instructions.feedback.email'),
            ],
            'mobile' => [
                ['required', (new \Propaganistas\LaravelPhone\Rules\Phone)->country('PH')->type('mobile')],
                config('instructions.feedback.mobile'),
            ],
            'webhook' => [
                ['required', 'url'],
                config('instructions.feedback.webhook'),
            ],
        ];
    }

    /**
     * Define validation rules for the data.
     */
    public static function rules(): array
    {
        return [
            'email' => ['nullable', 'email'],
            'mobile' => ['nullable', (new Phone)->country('PH')->type('mobile')],
            'webhook' => ['nullable', 'url'],
        ];
    }

    /**
     * Create an instance of FeedbackInstructionData from a comma-delimited input string.
     *
     * @return static
     */
    public static function fromText(string $text): self
    {
        // Trim the input to account for strings with whitespace only
        if (empty(trim($text))) {
            return new static(
                email: null,
                mobile: null,
                webhook: null
            );
        }

        // Split the input by comma
        $items = array_map('trim', explode(',', $text));

        $email = null;
        $mobile = null;
        $webhook = null;

        foreach ($items as $item) {
            // If already resolved, skip further processing
            if (! $email && filter_var($item, FILTER_VALIDATE_EMAIL)) {
                $email = $item;

                continue;
            }

            if (! $webhook && filter_var($item, FILTER_VALIDATE_URL)) {
                $webhook = $item;

                continue;
            }

            if (! $mobile && self::isValidMobile($item)) {
                $mobile = $item;

                continue;
            }
        }

        return new static(
            email: $email,
            mobile: $mobile,
            webhook: $webhook
        );
    }

    /**
     * Validate if the given string is a valid mobile number.
     */
    protected static function isValidMobile(string $mobile): bool
    {
        // Use Phone validation rule to check if it's a mobile number
        try {
            $validator = app('validator');
            $rules = ['mobile' => [(new Phone)->country('PH')->type('mobile')]];
            $data = ['mobile' => $mobile];

            $validation = $validator->make($data, $rules);

            return ! $validation->fails();
        } catch (\Exception $e) {
            return false; // Return false if validation fails unexpectedly
        }
    }

    public static function defaultEmail(): ?string
    {
        return config('instructions.feedback.email');
    }

    public static function defaultMobile(): ?string
    {
        return config('instructions.feedback.mobile');
    }

    public static function defaultWebhook(): ?string
    {
        return config('instructions.feedback.webhook');
    }
}
