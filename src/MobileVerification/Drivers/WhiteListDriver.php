<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\MobileVerification\Drivers;

use Illuminate\Support\Facades\Storage;
use LBHurtado\Voucher\MobileVerification\Concerns\NormalizesPhoneNumbers;
use LBHurtado\Voucher\MobileVerification\MobileVerificationDriverInterface;
use LBHurtado\Voucher\MobileVerification\MobileVerificationResult;

class WhiteListDriver implements MobileVerificationDriverInterface
{
    use NormalizesPhoneNumbers;

    public function verify(string $mobile, array $context = []): MobileVerificationResult
    {
        $normalized = $this->normalize($mobile);

        $allowedMobiles = $this->resolveAllowedMobiles($context);

        if (empty($allowedMobiles)) {
            return MobileVerificationResult::fail(
                'No whitelist configured. Set REDEMPTION_MOBILE_VERIFICATION_MOBILES or REDEMPTION_MOBILE_VERIFICATION_FILE.',
                $normalized,
            );
        }

        // Normalize all allowed mobiles for comparison
        $normalizedAllowed = array_map(fn ($m) => $this->normalize($m), $allowedMobiles);

        if (in_array($normalized, $normalizedAllowed, true)) {
            return MobileVerificationResult::pass($normalized, [
                'source' => $this->getSource($context),
                'list_size' => count($normalizedAllowed),
            ]);
        }

        return MobileVerificationResult::fail(
            'Mobile number is not in the allowed list.',
            $normalized,
            ['list_size' => count($normalizedAllowed)],
        );
    }

    /**
     * Resolve the full list of allowed mobiles from inline + CSV sources.
     */
    private function resolveAllowedMobiles(array $context): array
    {
        $mobiles = [];

        // Inline mobiles from config/env
        $inlineMobiles = $context['mobiles'] ?? [];
        if (is_array($inlineMobiles)) {
            $mobiles = array_merge($mobiles, array_filter($inlineMobiles));
        }

        // CSV file mobiles
        $file = $context['file'] ?? null;
        if ($file) {
            $csvMobiles = $this->loadFromCsv($file, $context['column'] ?? null);
            $mobiles = array_merge($mobiles, $csvMobiles);
        }

        return array_unique(array_filter($mobiles));
    }

    /**
     * Load mobile numbers from a CSV file in storage.
     */
    private function loadFromCsv(string $filePath, ?string $column): array
    {
        if (! Storage::disk('local')->exists($filePath)) {
            return [];
        }

        $content = Storage::disk('local')->get($filePath);
        $lines = array_filter(explode("\n", $content));

        if (empty($lines)) {
            return [];
        }

        // Parse header row
        $header = str_getcsv(array_shift($lines));
        $columnIndex = 0; // Default: first column

        if ($column !== null) {
            $foundIndex = array_search($column, $header, true);
            if ($foundIndex !== false) {
                $columnIndex = $foundIndex;
            }
        }

        // Extract mobile numbers
        $mobiles = [];
        foreach ($lines as $line) {
            $row = str_getcsv($line);
            if (isset($row[$columnIndex]) && trim($row[$columnIndex]) !== '') {
                $mobiles[] = trim($row[$columnIndex]);
            }
        }

        return $mobiles;
    }

    private function getSource(array $context): string
    {
        $sources = [];
        if (! empty($context['mobiles'])) {
            $sources[] = 'inline';
        }
        if (! empty($context['file'])) {
            $sources[] = 'csv:' . $context['file'];
        }

        return implode('+', $sources) ?: 'none';
    }
}
