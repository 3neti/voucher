<?php

declare(strict_types=1);

use Illuminate\Validation\ValidationException;
use VoucherV2Experiment\CampaignCase;
use VoucherV2Experiment\CampaignTemplate;
use VoucherV2Experiment\Contract;
use VoucherV2Experiment\EventCatalog;
use VoucherV2Experiment\ExecutionData;
use VoucherV2Experiment\LoanCase;
use VoucherV2Experiment\SettlementCase;

require_once __DIR__.'/../../experiments/v2/Contract.php';
require_once __DIR__.'/../../experiments/v2/Simulation.php';

function v2Example(string $driver = 'philhealth-bst'): array
{
    return [
        'schema' => 'voucher.execution.v2', 'driver' => 'settlement_envelope',
        'settlement' => ['driver' => $driver, 'version' => '1.0.0', 'readiness_gate' => 'settleable'],
        'value_flow' => ['destination' => 'instapay.bank.account:123456'],
        'events' => ['attest' => ['actor' => 'beneficiary', 'requires' => []], 'settle' => ['actor' => 'payer', 'requires' => ['settlement_ready', 'confirmed_payment']]],
    ];
}

it('defaults only missing schema to v1 and rejects unknown schema', function () {
    expect(Contract::parse(['driver' => 'default'])['schema'])->toBe('voucher.execution.v1')
        ->and(Contract::parse(['schema' => 'voucher.execution.v1', 'metadata' => ['legacy' => 1]])['metadata'])->toBe(['legacy' => 1]);
    expect(fn () => Contract::parse(['schema' => 'voucher.execution.v3']))->toThrow(InvalidArgumentException::class);
});

it('round trips typed v2 envelope and value flow with explicit events', function () {
    $input = v2Example();
    $parsed = Contract::parse($input);
    expect($parsed)->toBeInstanceOf(ExecutionData::class)
        ->and($parsed->settlement->driver)->toBe('philhealth-bst')
        ->and($parsed->value_flow->destination)->toBe('instapay.bank.account:123456')
        ->and($parsed->events['settle']->requires)->toBe(['settlement_ready', 'confirmed_payment'])
        ->and(data_get($parsed->toArray(), 'events.attest.actor'))->toBe('beneficiary')
        ->and(json_decode($parsed->toJson(), true)['schema'])->toBe('voucher.execution.v2');
});

it('accepts aui linked payment requirements without pretending the instruction proves payment', function () {
    $input = v2Example('aui.personal-accident');
    $input['settlement']['payment_requirement'] = 'linked_confirmed_payment';
    expect(Contract::parse($input)->settlement->payment_requirement)->toBe('linked_confirmed_payment');
});

it('rejects malformed v2 fields', function (string $path, mixed $value) {
    $input = v2Example();
    data_set($input, $path, $value);
    expect(fn () => Contract::parse($input))->toThrow(ValidationException::class);
})->with([
    ['driver', 'not-registered'], ['settlement.version', ''], ['value_flow.destination', ''],
    ['events.attest.actor', 'unauthorized'], ['events.attest.requires', 'not-array'], ['settlement.payment_requirement', 'paid'],
]);

it('makes selected notification events workflow specific', function () {
    $catalog = new EventCatalog(['attestation.recorded' => ['label' => 'Attestation recorded', 'channels' => ['webhook']], 'settlement.ready' => ['label' => 'Settlement ready', 'channels' => ['webhook']], 'internal.audit' => ['label' => 'Audit', 'channels' => []]]);
    $catalog->validate(['attestation.recorded', 'settlement.ready']);
    expect($catalog->label('attestation.recorded'))->toBe('Attestation recorded');
    expect(fn () => $catalog->validate(['internal.audit']))->toThrow(InvalidArgumentException::class);
    expect(fn () => $catalog->validate(['unknown']))->toThrow(InvalidArgumentException::class);
});

it('documents the experimental short-payment limitation and retains receipts when topped up', function () {
    // TODO: Issue an inactive Pay Code for agent resolution as agreed for AUI.
    $case = new CampaignCase('campaign-A', 5000, 0, 500);
    $case->recognize('payment-1', 'campaign-A', 4500, 'PHP', true);
    expect($case->state)->toBe('needs_resolution')->and($case->code)->toBeNull();
    expect(fn () => $case->issue(500))->toThrow(DomainException::class);
    $case->recognize('payment-2', 'campaign-A', 500, 'PHP', true);
    $case->issue(0);
    expect($case->state)->toBe('dormant')->and($case->receipts['payment-1']['amount_minor'])->toBe(4500)->and($case->issuances)->toBe(1);
    $case->activate(500);
    $case->activate(500);
    $case->issue(500);
    expect($case->state)->toBe('active')->and($case->charges)->toBe(1)->and($case->issuances)->toBe(1);
});

it('makes replay idempotent and rejects conflicting payment evidence', function () {
    $case = new CampaignCase('campaign-A', 5000, 0, 500);
    $case->recognize('p1', 'campaign-A', 5000, 'PHP', true);
    $case->recognize('p1', 'campaign-A', 5000, 'PHP', true);
    expect($case->collectedMinor)->toBe(5000)->and($case->receipts)->toHaveCount(1);
    expect(fn () => $case->recognize('p1', 'campaign-A', 4500, 'PHP', true))->toThrow(DomainException::class);
});

it('requires a matching authoritative payment', function (string $campaign, string $currency, bool $confirmed, int $amount) {
    $case = new CampaignCase('A', 5000, 0, 500);
    expect(fn () => $case->recognize('p1', $campaign, $amount, $currency, $confirmed))->toThrow(DomainException::class);
    expect($case->issuances)->toBe(0);
})->with([['B', 'PHP', true, 5000], ['A', 'USD', true, 5000], ['A', 'PHP', false, 5000], ['A', 'PHP', true, 0]]);

it('uses customer fee in payment due and holds overpayment for resolution', function () {
    $case = new CampaignCase('A', 5000, 500, 0);
    $case->recognize('p1', 'A', 5000, 'PHP', true);
    expect($case->state)->toBe('needs_resolution');
    $case->recognize('p2', 'A', 500, 'PHP', true);
    $case->issue(0);
    expect($case->state)->toBe('active')->and($case->charges)->toBe(0);
    $over = new CampaignCase('A', 5000, 0, 500);
    $over->recognize('p3', 'A', 5100, 'PHP', true);
    expect($over->state)->toBe('needs_resolution');
});

it('models philhealth attestation readiness payment and pending or failed payout', function (string $outcome, string $expected) {
    $case = new SettlementCase;
    expect(fn () => $case->pay('payment', true))->toThrow(DomainException::class);
    $case->attest('attest');
    $case->attest('attest');
    expect($case->state)->toBe('awaiting_review');
    expect(fn () => $case->pay('payment', true))->toThrow(DomainException::class);
    $case->approve();
    expect(fn () => $case->pay('payment', false))->toThrow(DomainException::class);
    $case->pay('payment', true);
    $case->pay('payment', true);
    expect($case->state)->toBe('payout_pending')->and($case->payouts)->toBe(1);
    $case->payout($outcome);
    expect($case->state)->toBe($expected);
})->with([['confirmed', 'settled'], ['failed', 'recovery_required']]);

it('models the role and authentication boundaries for the PhilHealth sequence', function () {
    $case = new SettlementCase(payerAuthenticationRequired: true);
    expect(fn () => $case->attest('one', 'hospital_reviewer'))->toThrow(DomainException::class);
    $case->attest('one');
    expect(fn () => $case->approve('hospital_reviewer', false))->toThrow(DomainException::class);
    expect(fn () => $case->approve('payer', true))->toThrow(DomainException::class);
    expect(fn () => $case->pay('payment', true, 'payer', true))->toThrow(DomainException::class);
    $case->approve();
    expect(fn () => $case->pay('payment', true))->toThrow(DomainException::class);
    $case->pay('payment', true, 'payer', true);
    expect($case->state)->toBe('payout_pending')->and($case->payouts)->toBe(1);
});

it('models loan disbursement before four fixed repayments with replay protection', function () {
    $case = new LoanCase(12000, 3000);
    expect(fn () => $case->pay('p1', 3000))->toThrow(DomainException::class);
    expect(fn () => $case->disburse(false))->toThrow(DomainException::class);
    $case->disburse(true);
    expect(fn () => $case->pay('bad', 2900))->toThrow(DomainException::class);
    foreach (['p1', 'p2', 'p3', 'p4'] as $id) {
        $case->pay($id, 3000);
    }
    $case->pay('p4', 3000);
    expect($case->state)->toBe('settled')->and($case->paidMinor)->toBe(12000);
    expect(fn () => $case->pay('p4', 2000))->toThrow(DomainException::class);
});

it('pins the campaign template and produces one preview from the compiled instruction', function () {
    $execution = v2Example('aui.personal-accident');
    $execution['settlement']['payment_requirement'] = 'linked_confirmed_payment';
    $execution['events'] = ['complete_application' => ['actor' => 'beneficiary'], 'complete_product' => ['actor' => 'system', 'requires' => ['payment_requirement_satisfied', 'application_complete']]];
    $definition = ['execution' => $execution];
    $template = new CampaignTemplate('aui.application', '2.0.0', $definition);
    $instruction = $template->compile(5000, 'PHP');
    $preview = CampaignTemplate::preview('AA317', $instruction);
    expect($instruction['target_amount'])->toBe(50)
        ->and($instruction['cash']['amount'])->toBe(0)
        ->and($instruction['template']['definition_hash'])->toBe($template->definitionHash)
        ->and($preview['engineering'])->toBe($instruction)
        ->and(array_column($preview['screens'], 'code'))->toBe(['AA317', 'AA317']);
    $definition['execution']['settlement']['version'] = '2.0.0';
    $newTemplate = new CampaignTemplate('aui.application', '2.0.0', $definition);
    expect($newTemplate->definitionHash)->not->toBe($template->definitionHash);
});

it('rejects a campaign template without the linked payment requirement', function () {
    $template = new CampaignTemplate('aui.application', '2.0.0', ['execution' => v2Example('aui.personal-accident')]);
    expect(fn () => $template->compile(5000, 'PHP'))->toThrow(InvalidArgumentException::class);
});
