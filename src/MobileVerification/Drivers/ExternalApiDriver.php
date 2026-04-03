<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\MobileVerification\Drivers;

use Illuminate\Support\Facades\Http;
use LBHurtado\Voucher\MobileVerification\Concerns\NormalizesPhoneNumbers;
use LBHurtado\Voucher\MobileVerification\MobileVerificationDriverInterface;
use LBHurtado\Voucher\MobileVerification\MobileVerificationResult;

class ExternalApiDriver implements MobileVerificationDriverInterface
{
    use NormalizesPhoneNumbers;

    public function verify(string $mobile, array $context = []): MobileVerificationResult
    {
        $normalized = $this->normalize($mobile);

        $url = $context['url'] ?? null;
        if (! $url) {
            return MobileVerificationResult::fail(
                'External API URL not configured. Set REDEMPTION_MOBILE_VERIFICATION_API_URL.',
                $normalized,
            );
        }

        $method = strtoupper($context['method'] ?? 'POST');
        $mobileParam = $context['mobile_param'] ?? 'mobile';
        $timeout = (int) ($context['timeout'] ?? 5);
        $headers = $context['headers'] ?? ['Accept' => 'application/json'];
        $extraParams = $context['extra_params'] ?? [];
        $responseField = $context['response_field'] ?? 'valid';

        $params = array_merge($extraParams, [$mobileParam => $normalized]);

        try {
            $request = Http::timeout($timeout)->withHeaders($headers);

            $response = match ($method) {
                'GET' => $request->get($url, $params),
                default => $request->post($url, $params),
            };

            if (! $response->successful()) {
                return MobileVerificationResult::fail(
                    sprintf('External API returned HTTP %d.', $response->status()),
                    $normalized,
                    ['http_status' => $response->status()],
                );
            }

            $isValid = (bool) data_get($response->json(), $responseField, false);

            if ($isValid) {
                return MobileVerificationResult::pass($normalized, [
                    'api_url' => $url,
                ]);
            }

            return MobileVerificationResult::fail(
                'Mobile number not found in external system.',
                $normalized,
                ['api_url' => $url],
            );
        } catch (\Throwable $e) {
            return MobileVerificationResult::fail(
                sprintf('External API error: %s', $e->getMessage()),
                $normalized,
                ['exception' => get_class($e)],
            );
        }
    }
}
