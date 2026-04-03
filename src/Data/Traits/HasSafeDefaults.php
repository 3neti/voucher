<?php

namespace LBHurtado\Voucher\Data\Traits;

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

trait HasSafeDefaults
{
    /** @var bool Enable verbose validation logging */
    private const DEBUG = false;

    /**
     * Loop over your rulesAndDefaults() map, validate each property,
     * and overwrite it with either the validated value or its default.
     */
    protected function applyRulesAndDefaults(): void
    {
        $class = static::class;

        foreach ($this->rulesAndDefaults() as $key => [$rules, $default]) {
            $raw = $this->{$key};
            $toTest = $raw;

            // 🔑 If this is TTL and you have a CarbonInterval, convert it to ISO
            if ($key === 'ttl' && $raw instanceof CarbonInterval) {
                // Carbon 2.x+ has toISOString():
                $toTest = method_exists($raw, 'toISOString')
                    ? $raw->toISOString()
                    : $this->formatIso($raw);
            }

            $validator = Validator::make(
                [$key => $toTest],
                [$key => $rules]
            );
            if ($validator->fails()) {
                if (self::DEBUG) {
                    Log::debug("[{$class}] \"{$key}\" failed validation, falling back to default", [
                        'raw' => $toTest,
                        'default' => $default,
                        'errors' => $validator->errors()->all(),
                    ]);
                }

                $this->{$key} = $default;
            } else {
                $validated = $validator->validated()[$key];
                if (self::DEBUG) {
                    Log::debug("[{$class}] \"{$key}\" validated successfully", [
                        'raw' => $toTest,
                        'validated' => $validated,
                    ]);
                }

                // Put it back into the property—if TTL, cast back to CarbonInterval
                if ($key === 'ttl') {
                    $this->ttl = CarbonInterval::make($validated);
                } else {
                    $this->{$key} = $validated;
                }
            }
        }
    }

    /**
     * Fallback for ISO‐string formatting if CarbonInterval::toISOString() isn’t available.
     */
    protected function formatIso(CarbonInterval $i): string
    {
        return sprintf(
            'P%s%s%sT%s%s%s',
            $i->years ? "{$i->years}Y" : '',
            $i->months ? "{$i->months}M" : '',
            $i->days ? "{$i->days}D" : '',
            $i->hours ? "{$i->hours}H" : '',
            $i->minutes ? "{$i->minutes}M" : '',
            $i->seconds ? "{$i->seconds}S" : ''
        );
    }

    /**
     * Each class using this trait must define this method.
     *
     * @return array<string, array{0: array<string>, 1: mixed}>
     */
    abstract protected function rulesAndDefaults(): array;

    //    protected function formatIso(CarbonInterval $i): string
    //    {
    //        // naive manual builder; tweak as needed
    //        return sprintf(
    //            'P%s%s%sT%s%s%s',
    //            $i->years  ? "{$i->years}Y"  : '',
    //            $i->months ? "{$i->months}M" : '',
    //            $i->days   ? "{$i->days}D"   : '',
    //            $i->hours  ? "{$i->hours}H"  : '',
    //            $i->minutes? "{$i->minutes}M": '',
    //            $i->seconds? "{$i->seconds}S": ''
    //        );
    //    }
}
