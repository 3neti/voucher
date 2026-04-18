<?php

namespace LBHurtado\Voucher\Validators;

use Illuminate\Support\Carbon;
use LBHurtado\Voucher\Contracts\RedemptionRuleValidator;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Data\RedemptionValidationIssueData;
use LBHurtado\Voucher\Enums\RedemptionValidationCode;
use LBHurtado\Voucher\Enums\RedemptionValidationSeverity;
use LBHurtado\Voucher\Models\Voucher;

class TimeRuleValidator implements RedemptionRuleValidator
{
    public function supports(Voucher $voucher): bool
    {
        $time = $voucher->instructions?->validation?->time;

        return $time !== null
            && (
                $time->window !== null
                || $time->limit_minutes !== null
                || $time->track_duration === true
            );
    }

    public function validate(Voucher $voucher, RedemptionEvidenceData $evidence): array
    {
        $issues = [];
        $time = $voucher->instructions?->validation?->time;

        if (! $time) {
            return [];
        }

        $severity = RedemptionValidationSeverity::BLOCK;

        // Window check
        if ($time->window) {
            $timezone = $time->window->timezone;
            $now = now($timezone);

            $windowStart = Carbon::createFromFormat(
                'H:i',
                $time->window->start_time,
                $timezone
            )->setDate($now->year, $now->month, $now->day);

            $windowEnd = Carbon::createFromFormat(
                'H:i',
                $time->window->end_time,
                $timezone
            )->setDate($now->year, $now->month, $now->day);

            // Support overnight windows like 22:00–02:00
            if ($windowEnd->lessThan($windowStart)) {
                if ($now->lessThan($windowStart)) {
                    $windowStart->subDay();
                } else {
                    $windowEnd->addDay();
                }
            }

            if (! $now->betweenIncluded($windowStart, $windowEnd)) {
                $issues[] = new RedemptionValidationIssueData(
                    field: 'time',
                    code: RedemptionValidationCode::OUTSIDE_TIME_WINDOW,
                    severity: $severity,
                    message: 'Redemption is outside the allowed time window.',
                    context: [
                        'now' => $now->toIso8601String(),
                        'window_start' => $windowStart->toIso8601String(),
                        'window_end' => $windowEnd->toIso8601String(),
                        'timezone' => $timezone,
                    ],
                );
            }
        }

        // Duration / limit check
        if (
            $time->limit_minutes !== null
            && $time->track_duration === true
            && $voucher->starts_at !== null
        ) {
            $startedAt = $voucher->starts_at instanceof Carbon
                ? $voucher->starts_at
                : Carbon::parse($voucher->starts_at);

            $elapsedMinutes = $startedAt->diffInMinutes(now(), false);

            if ($elapsedMinutes > $time->limit_minutes) {
                $issues[] = new RedemptionValidationIssueData(
                    field: 'time',
                    code: RedemptionValidationCode::TIME_LIMIT_EXCEEDED,
                    severity: $severity,
                    message: 'Redemption exceeded the allowed time limit.',
                    context: [
                        'started_at' => $startedAt->toIso8601String(),
                        'elapsed_minutes' => $elapsedMinutes,
                        'limit_minutes' => $time->limit_minutes,
                    ],
                );
            }
        }

        return $issues;
    }
}