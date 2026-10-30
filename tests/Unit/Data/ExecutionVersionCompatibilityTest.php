<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionInstructionData;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Exceptions\UnsupportedExecutionInstructionSchemaException;
use LBHurtado\Voucher\Services\ExecutionEngine;

it('defaults old instructions to v1 and preserves v2 execution fields', function (): void {
    $base = ['cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => ['country' => 'PH']], 'inputs' => ['fields' => []], 'feedback' => [], 'rider' => [], 'count' => 1, 'prefix' => 'PAY', 'mask' => '****'];
    $v1 = VoucherInstructionsData::createFromAttribs([...$base, 'execution' => ['driver' => 'default']]);
    $v2 = VoucherInstructionsData::createFromAttribs([...$base, 'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope', 'settlement' => ['driver' => 'philhealth-bst', 'readiness_gate' => 'settleable'], 'value_flow' => ['destination' => 'instapay.gcash.mobile:09179999999'], 'events' => ['attest' => ['actor' => 'beneficiary']]]]);
    expect($v1->execution->schema)->toBe('voucher.execution.v1')
        ->and($v2->execution->schema)->toBe('voucher.execution.v2')
        ->and($v2->execution->settlement['driver'])->toBe('philhealth-bst')
        ->and($v2->execution->value_flow['destination'])->toBe('instapay.gcash.mobile:09179999999')
        ->and($v2->execution->events['attest']['actor'])->toBe('beneficiary');
});

it('rejects unsupported future execution schema', function (): void {
    expect(fn () => ExecutionInstructionData::from(['schema' => 'voucher.execution.v3']))->toThrow(UnsupportedExecutionInstructionSchemaException::class);
});

it('round trips v2 voucher fields without adding them to v1 instructions', function (): void {
    $base = ['cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => ['country' => 'PH']], 'inputs' => ['fields' => []], 'feedback' => [], 'rider' => [], 'count' => 1, 'prefix' => 'PAY', 'mask' => '****'];
    $v1 = VoucherInstructionsData::createFromAttribs($base)->toArray();
    $fields = [
        'feedback' => ['webhooks' => [['url' => 'https://example.org/events', 'events' => ['attestation.recorded']]]],
        'presentation' => ['stamp' => ['design_id' => 'simple', 'design_version' => 1], 'share' => ['title' => 'Receipt']],
        'pricing' => ['quote_reference' => 'quote:1', 'currency' => 'PHP', 'fee_bearer' => 'issuer'],
        'campaign' => ['campaign_reference' => 'campaign:1', 'campaign_revision' => '1', 'template_id' => 'aui', 'template_version' => '2', 'definition_hash' => str_repeat('a', 64)],
    ];
    $v2 = VoucherInstructionsData::createFromAttribs([...$base, ...$fields, 'execution' => ['schema' => 'voucher.execution.v2']])->toArray();

    expect($v1)->not->toHaveKeys(['presentation', 'pricing', 'campaign'])
        ->and($v1['feedback'])->not->toHaveKey('webhooks')
        ->and($v2['feedback']['webhooks'])->toBe($fields['feedback']['webhooks'])
        ->and($v2['presentation'])->toBe($fields['presentation'])
        ->and($v2['pricing'])->toBe($fields['pricing'])
        ->and($v2['campaign'])->toBe($fields['campaign']);
});

it('rejects v2 voucher fields on legacy instructions', function (): void {
    $base = ['cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => ['country' => 'PH']], 'inputs' => ['fields' => []], 'feedback' => [], 'rider' => [], 'count' => 1, 'prefix' => 'PAY', 'mask' => '****'];
    expect(fn () => VoucherInstructionsData::createFromAttribs([...$base, 'presentation' => ['share' => ['title' => 'Receipt']]]))
        ->toThrow(ValidationException::class);
});

it('fails closed instead of silently ignoring v2 event declarations', function (): void {
    $instruction = ExecutionInstructionData::from(['schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope', 'events' => ['attest' => ['actor' => 'beneficiary']]]);
    $context = new ExecutionContextData(contact: null, voucherCode: 'AA317', instruction: $instruction);
    expect(fn () => app(ExecutionEngine::class)->execute($context))->toThrow(LogicException::class, 'refusing to ignore');
});
