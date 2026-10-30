<?php

declare(strict_types=1);

namespace VoucherV2Experiment;

use InvalidArgumentException;

/** Experimental instruction and walkthrough; no routing, authentication or provider execution. */
final class PhilhealthWorkflow
{
    public static function instruction(int $targetMinor, string $destination, int $collectionWalletId, string $payerAuthentication = 'optional'): VoucherInstructionV2
    {
        if ($targetMinor <= 0 || trim($destination) === '' || $collectionWalletId <= 0 || ! in_array($payerAuthentication, ['optional', 'required'], true)) {
            throw new InvalidArgumentException('A positive target, destination and supported payer authentication are required.');
        }

        return VoucherInstructionV2Factory::make([
            'cash' => ['amount' => 0, 'currency' => 'PHP', 'settlement_rail' => 'INSTAPAY', 'validation' => ['country' => 'PH']],
            'inputs' => ['fields' => ['name']],
            'count' => 1, 'prefix' => 'PAY', 'mask' => '****',
            'voucher_type' => 'settlement', 'target_amount' => $targetMinor / 100,
            'metadata' => ['collection_wallet_id' => $collectionWalletId],
            'execution' => [
                'schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope',
                'settlement' => ['driver' => 'philhealth-bst', 'version' => '1.0.0', 'readiness_gate' => 'settleable'],
                'value_flow' => ['destination' => $destination],
                'events' => [
                    'attest' => ['actor' => 'beneficiary', 'requires' => [], 'surface' => 'claim', 'authentication' => 'anonymous'],
                    'review' => ['actor' => 'hospital_reviewer', 'requires' => ['attestation_recorded'], 'surface' => 'claim', 'authentication' => 'required'],
                    'settle' => ['actor' => 'payer', 'requires' => ['settlement_ready', 'confirmed_payment'], 'surface' => 'pay', 'authentication' => $payerAuthentication],
                ],
            ],
        ], new EventCatalog([]));
    }

    /** @return array<string,mixed> */
    public static function walkthrough(VoucherInstructionV2 $instruction, string $code): array
    {
        if (! preg_match('/\A[A-Za-z0-9]{3,32}\z/', $code)) {
            throw new InvalidArgumentException('A valid Pay Code is required for the walkthrough.');
        }

        $execution = $instruction->execution;
        if ($execution->settlement?->driver !== 'philhealth-bst') {
            throw new InvalidArgumentException('Expected a PhilHealth settlement instruction.');
        }
        $screens = [];
        foreach (['attest', 'review', 'settle'] as $key) {
            $event = $execution->events[$key] ?? null;
            $expected = match ($key) {
                'attest' => ['beneficiary', 'claim', 'anonymous'],
                'review' => ['hospital_reviewer', 'claim', 'required'],
                'settle' => ['payer', 'pay', null],
            };
            if (! $event instanceof EventData || $event->actor !== $expected[0] || $event->surface !== $expected[1]
                || ($expected[2] !== null && $event->authentication !== $expected[2])
                || ($key === 'settle' && ! in_array($event->authentication, ['optional', 'required'], true))
                || ($key === 'attest' && $event->requires !== [])
                || ($key === 'review' && $event->requires !== ['attestation_recorded'])
                || ($key === 'settle' && $event->requires !== ['settlement_ready', 'confirmed_payment'])) {
                throw new InvalidArgumentException('Unsupported PhilHealth walkthrough event.');
            }
            $screens[] = [
                'event' => $key, 'actor' => $event->actor, 'path' => '/x/'.$event->surface.'/'.$code,
                'authentication' => $event->authentication, 'requires' => $event->requires,
                'waiting' => match ($key) {
                    'attest' => 'waiting_for_hospital_review',
                    'review' => 'waiting_for_payment',
                    'settle' => 'waiting_for_provider_confirmation',
                },
            ];
        }

        return [
            'issuer' => ['actor' => 'hospital', 'path' => '/x/cockpit/quick-generate', 'authentication' => 'required'],
            'code' => $code, 'instruction' => $instruction->instructionArray(), 'screens' => $screens,
        ];
    }
}
