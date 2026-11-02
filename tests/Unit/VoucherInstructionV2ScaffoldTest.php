<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Data\VoucherPaymentDetailFieldData;
use LBHurtado\Voucher\Data\VoucherPaymentDetailsData;
use LBHurtado\Voucher\Data\VoucherPaymentDocumentData;
use VoucherV2Experiment\EventCatalog;
use VoucherV2Experiment\PhilhealthWorkflow;
use VoucherV2Experiment\VoucherInstructionV2Factory;

require_once __DIR__.'/../../experiments/v2/Contract.php';
require_once __DIR__.'/../../experiments/v2/VoucherInstructionV2.php';
require_once __DIR__.'/../../experiments/v2/PhilhealthWorkflow.php';

it('models the hospital issuer, patient, hospital review and payer in one v2 instruction and walkthrough', function () {
    $instruction = PhilhealthWorkflow::instruction(2_000_000, 'instapay.bank.account:123456', 1);
    $array = $instruction->instructionArray();
    $preview = PhilhealthWorkflow::walkthrough($instruction, 'AA317');

    expect(data_get($array, 'cash.amount'))->toEqual(0)
        ->and(data_get($array, 'target_amount'))->toEqual(20000)
        ->and(data_get($array, 'execution.value_flow.destination'))->toBe('instapay.bank.account:123456')
        ->and(data_get($array, 'execution.events.review.actor'))->toBe('hospital_reviewer')
        ->and(data_get($array, 'execution.events.review.requires'))->toBe(['attestation_recorded'])
        ->and($preview['issuer'])->toBe(['actor' => 'hospital', 'path' => '/x/cockpit/quick-generate', 'authentication' => 'required'])
        ->and(array_column($preview['screens'], 'path'))->toBe(['/x/claim/AA317', '/x/claim/AA317', '/x/pay/AA317'])
        ->and(array_column($preview['screens'], 'authentication'))->toBe(['anonymous', 'required', 'optional'])
        ->and(array_column($preview['screens'], 'waiting'))->toBe(['waiting_for_hospital_review', 'waiting_for_payment', 'waiting_for_provider_confirmation'])
        ->and($preview['instruction'])->toBe($array);

    $reloaded = VoucherInstructionV2Factory::make($array, new EventCatalog([]));
    expect(PhilhealthWorkflow::walkthrough($reloaded, 'AA317'))->toBe($preview);
});

it('supports an authenticated payer and rejects unsafe PhilHealth walkthrough declarations', function () {
    $instruction = PhilhealthWorkflow::instruction(10000, 'instapay.bank.account:123456', 1, 'required');
    expect(PhilhealthWorkflow::walkthrough($instruction, 'AA317')['screens'][2]['authentication'])->toBe('required');
    $instruction->execution->events['review']->authentication = 'anonymous';
    expect(fn () => PhilhealthWorkflow::walkthrough($instruction, 'AA317'))->toThrow(InvalidArgumentException::class);
    expect(fn () => PhilhealthWorkflow::walkthrough(PhilhealthWorkflow::instruction(10000, 'instapay.bank.account:123456', 1), '../bad'))->toThrow(InvalidArgumentException::class);
});

function scaffoldCatalog(): EventCatalog
{
    return new EventCatalog([
        'settlement.ready' => ['label' => 'Ready', 'channels' => ['webhook']],
        'application.completed' => ['label' => 'Application completed', 'channels' => ['webhook']],
    ]);
}

function scaffoldInstruction(string $driver = 'philhealth-bst'): array
{
    return [
        'cash' => ['amount' => 0, 'currency' => 'PHP', 'validation' => ['country' => 'PH']],
        'inputs' => ['fields' => ['name']],
        'rider' => ['message' => 'Complete this application'],
        'count' => 1, 'prefix' => 'PAY', 'mask' => '****',
        'voucher_type' => 'settlement', 'target_amount' => 50,
        'metadata' => ['collection_wallet_id' => 1, 'custom' => ['legacy_marker' => 'preserved']],
        'execution' => [
            'schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope',
            'settlement' => ['driver' => $driver, 'version' => '1.0.0', 'readiness_gate' => 'settleable'],
            'value_flow' => ['destination' => 'instapay.bank.account:123456'],
            'events' => ['attest' => ['actor' => 'beneficiary'], 'settle' => ['actor' => 'payer', 'requires' => ['settlement_ready']]],
        ],
        'feedback' => ['email' => 'issuer@example.test', 'webhooks' => [['url' => 'https://issuer.example.test/hooks', 'events' => ['settlement.ready']]]],
        'presentation' => ['stamp' => ['design_id' => 'steampunk', 'design_version' => 8, 'show_logo' => true], 'share' => ['title' => 'Settlement', 'image_source' => 'stamp']],
        'pricing' => ['quote_reference' => 'quote:123', 'currency' => 'PHP', 'fee_bearer' => 'issuer', 'quote_hash' => str_repeat('a', 64)],
        'campaign' => ['campaign_reference' => 'campaign:1', 'campaign_revision' => '1', 'template_id' => 'aui.application', 'template_version' => '2.0.0', 'definition_hash' => str_repeat('b', 64), 'payment_recognition_reference' => 'recognition:1'],
    ];
}

it('round trips typed v2 payment details and rejects them under v1', function () {
    $input = scaffoldInstruction();
    $input['payment_details'] = [
        'description' => 'Laptop repair',
        'reference' => 'INV-001',
        'fields' => [['label' => 'Service', 'value' => 'Screen']],
        'documents' => [['id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'type' => 'invoice', 'label' => 'Invoice']],
    ];
    $details = VoucherInstructionsData::createFromAttribs($input)->payment_details;
    expect($details)->toBeInstanceOf(VoucherPaymentDetailsData::class)
        ->and($details->fields[0])->toBeInstanceOf(VoucherPaymentDetailFieldData::class)
        ->and($details->documents[0])->toBeInstanceOf(VoucherPaymentDocumentData::class)
        ->and($details->toArray())->toBe($input['payment_details']);
    unset($input['execution']);
    expect(fn () => VoucherInstructionsData::createFromAttribs($input))->toThrow(ValidationException::class);
});

it('rejects invalid or misplaced payment details', function (callable $change) {
    $input = scaffoldInstruction();
    $input['payment_details'] = [
        'documents' => [['id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV', 'type' => 'invoice', 'label' => 'Invoice']],
    ];
    $change($input);
    expect(fn () => VoucherInstructionsData::createFromAttribs($input))->toThrow(ValidationException::class);
})->with([
    'disbursable voucher' => [function (array &$input): void {
        $input['voucher_type'] = 'redeemable';
    }],
    'invalid document id' => [function (array &$input): void {
        $input['payment_details']['documents'][0]['id'] = 'not-a-ulid';
    }],
    'duplicate documents' => [function (array &$input): void {
        $input['payment_details']['documents'][] = $input['payment_details']['documents'][0];
    }],
    'unapproved attributes' => [function (array &$input): void {
        $input['payment_details']['documents'][0]['url'] = 'https://example.test';
    }],
]);

it('scaffolds and serializes typed voucher-level v2 sections with the real v1 core', function () {
    $instruction = VoucherInstructionV2Factory::make(scaffoldInstruction(), scaffoldCatalog());
    $array = $instruction->instructionArray();
    expect($instruction->core)->toBeInstanceOf(VoucherInstructionsData::class)
        ->and(data_get($array, 'execution.schema'))->toBe('voucher.execution.v2')
        ->and(data_get($array, 'execution.settlement.driver'))->toBe('philhealth-bst')
        ->and(data_get($array, 'execution.value_flow.destination'))->toBe('instapay.bank.account:123456')
        ->and(data_get($array, 'feedback.webhooks.0.events'))->toBe(['settlement.ready'])
        ->and(data_get($array, 'presentation.stamp.design_version'))->toBe(8)
        ->and(data_get($array, 'pricing.quote_reference'))->toBe('quote:123')
        ->and(data_get($array, 'campaign.payment_recognition_reference'))->toBe('recognition:1')
        ->and(data_get($array, 'metadata.custom.legacy_marker'))->toBe('preserved');
    $reloaded = VoucherInstructionV2Factory::make($array, scaffoldCatalog())->instructionArray();
    expect(data_get($reloaded, 'execution'))->toBe(data_get($array, 'execution'))
        ->and(data_get($reloaded, 'feedback'))->toBe(data_get($array, 'feedback'))
        ->and(data_get($reloaded, 'presentation'))->toBe(data_get($array, 'presentation'))
        ->and(data_get($reloaded, 'pricing'))->toBe(data_get($array, 'pricing'))
        ->and(data_get($reloaded, 'campaign'))->toBe(data_get($array, 'campaign'));
});

it('accepts an AUI configuration with a linked payment requirement', function () {
    $input = scaffoldInstruction('aui.personal-accident');
    $input['execution']['settlement']['payment_requirement'] = 'linked_confirmed_payment';
    $instruction = VoucherInstructionV2Factory::make($input, scaffoldCatalog());
    expect($instruction->execution->settlement->payment_requirement)->toBe('linked_confirmed_payment');
});

it('rejects an unsupported webhook subscription', function () {
    $input = scaffoldInstruction();
    $input['feedback']['webhooks'][0]['events'] = ['unknown.event'];
    expect(fn () => VoucherInstructionV2Factory::make($input, scaffoldCatalog()))->toThrow(InvalidArgumentException::class);
});

it('rejects invalid new fields', function (string $path, mixed $value) {
    $input = scaffoldInstruction();
    data_set($input, $path, $value);
    expect(fn () => VoucherInstructionV2Factory::make($input, scaffoldCatalog()))->toThrow(ValidationException::class);
})->with([
    ['feedback.webhooks.0.url', 'not-url'], ['presentation.stamp.design_version', 0],
    ['pricing.fee_bearer', 'third-party'], ['campaign.definition_hash', 'bad-hash'],
]);

it('does not change v1 execution defaults or webhook representation', function () {
    $v1 = scaffoldInstruction();
    unset($v1['execution'], $v1['presentation'], $v1['pricing'], $v1['campaign']);
    $v1['feedback'] = ['webhook' => 'https://issuer.example.test/hooks'];
    $core = VoucherInstructionsData::createFromAttribs($v1);
    expect($core->executionInstruction()->schema)->toBe('voucher.execution.v1')
        ->and($core->feedback->webhook)->toBe('https://issuer.example.test/hooks');
});
