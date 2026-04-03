<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\MobileVerification\Drivers;

use Illuminate\Support\Facades\DB;
use LBHurtado\Voucher\MobileVerification\Concerns\NormalizesPhoneNumbers;
use LBHurtado\Voucher\MobileVerification\MobileVerificationDriverInterface;
use LBHurtado\Voucher\MobileVerification\MobileVerificationResult;

class ExternalDbDriver implements MobileVerificationDriverInterface
{
    use NormalizesPhoneNumbers;

    public function verify(string $mobile, array $context = []): MobileVerificationResult
    {
        $normalized = $this->normalize($mobile);

        $connection = $context['connection'] ?? null;
        $table = $context['table'] ?? null;
        $column = $context['column'] ?? null;

        if (! $connection || ! $table || ! $column) {
            return MobileVerificationResult::fail(
                'External DB not fully configured. Set REDEMPTION_MOBILE_VERIFICATION_DB_CONNECTION, _DB_TABLE, and _DB_COLUMN.',
                $normalized,
            );
        }

        try {
            $query = DB::connection($connection)->table($table);

            // Apply additional where conditions if configured
            $where = $context['where'] ?? [];
            foreach ($where as $key => $value) {
                $query->where($key, $value);
            }

            // Check both normalized and raw formats
            $exists = $query->clone()->where($column, $normalized)->exists();

            if (! $exists) {
                // Also try with raw input (in case DB stores in different format)
                $exists = $query->clone()->where($column, $mobile)->exists();
            }

            if ($exists) {
                return MobileVerificationResult::pass($normalized, [
                    'connection' => $connection,
                    'table' => $table,
                ]);
            }

            return MobileVerificationResult::fail(
                'Mobile number is not in the beneficiary list.',
                $normalized,
                ['connection' => $connection, 'table' => $table],
            );
        } catch (\Throwable $e) {
            return MobileVerificationResult::fail(
                sprintf('External DB error: %s', $e->getMessage()),
                $normalized,
                ['exception' => get_class($e)],
            );
        }
    }
}
