<?php

namespace LBHurtado\Voucher\Data\Casts;

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Log;
use Spatie\LaravelData\Casts\Cast;
use Spatie\LaravelData\Support\Creation\CreationContext;
use Spatie\LaravelData\Support\DataProperty;

class CarbonIntervalCast implements Cast
{
    /** @var bool Enable verbose casting logging */
    private const DEBUG = false;

    public function cast(
        DataProperty $property,
        mixed $value,
        array $properties,
        CreationContext $context
    ): mixed {
        $name = $property->name;
        if (self::DEBUG) {
            Log::debug("[CarbonIntervalCast] Casting \"{$name}\"", ['raw' => $value, 'type' => gettype($value)]);
        }

        // Already a CarbonInterval?
        if ($value instanceof CarbonInterval) {
            if (self::DEBUG) {
                Log::debug("[CarbonIntervalCast] \"{$name}\" is already a CarbonInterval, returning as-is");
            }

            return $value;
        }

        // Empty string -> null
        if ($value === '') {
            if (self::DEBUG) {
                Log::debug("[CarbonIntervalCast] \"{$name}\" is empty string, casting to null");
            }

            return null;
        }

        // Null stays null
        if ($value === null) {
            if (self::DEBUG) {
                Log::debug("[CarbonIntervalCast] \"{$name}\" is null, returning null");
            }

            return null;
        }

        // Array → reconstructfrom serialized CarbonInterval
        if (is_array($value) && isset($value['d'])) {
            if (self::DEBUG) {
                Log::debug("[CarbonIntervalCast] \"{$name}\" array with 'd' key, reconstructing CarbonInterval");
            }

            return CarbonInterval::days($value['d'])
                ->addMonths($value['m'] ?? 0)
                ->addYears($value['y'] ?? 0)
                ->addHours($value['h'] ?? 0)
                ->addMinutes($value['i'] ?? 0)
                ->addSeconds($value['s'] ?? 0);
        }

        // Numeric → seconds
        if (is_numeric($value)) {
            if (self::DEBUG) {
                Log::debug("[CarbonIntervalCast] \"{$name}\" numeric, interpreting as seconds");
            }

            return CarbonInterval::seconds((int) $value);
        }

        // String → try parse
        if (is_string($value)) {
            if (self::DEBUG) {
                Log::debug("[CarbonIntervalCast] \"{$name}\" string, attempting CarbonInterval::make()");
            }
            try {
                $ci = CarbonInterval::make($value);
                if (self::DEBUG) {
                    Log::debug("[CarbonIntervalCast] \"{$name}\" parsed successfully", ['interval' => $ci]);
                }

                return $ci;
            } catch (\Throwable $e) {
                Log::error("[CarbonIntervalCast] “{$name}” failed to parse as CarbonInterval", [
                    'value' => $value,
                    'error' => $e->getMessage(),
                ]);
                throw new \InvalidArgumentException("Cannot cast “{$name}” to CarbonInterval: {$value}");
            }
        }

        // Anything else → fatal
        Log::error("[CarbonIntervalCast] “{$name}” unsupported type", ['value' => $value]);
        throw new \InvalidArgumentException("Cannot cast “{$name}” to CarbonInterval: ".print_r($value, true));
    }
}
