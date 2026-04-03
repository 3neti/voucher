<?php

namespace LBHurtado\Voucher\Rules;

use Carbon\CarbonInterval;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidISODuration implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }
        $ci = CarbonInterval::fromString($value);
        if ($ci->total('minutes') == 0.0) {
            $fail('The :attribute must be a valid ISO-8601 duration string (e.g. PT1H30M or P2D).');
        }
        //        try {
        //            $ci = CarbonInterval::fromString($value);
        //            dd($ci->total('minutes'));
        //        } catch (\Exception) {
        //            $fail('The :attribute must be a valid ISO-8601 duration string (e.g. PT1H30M or P2D).');
        //        }
    }
}
