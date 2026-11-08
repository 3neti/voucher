<?php

use Illuminate\Validation\ValidationException;
use LBHurtado\Voucher\Data\VoucherInstructionsData;

function claimDocumentsPayload(): array
{
    return [
        'cash' => ['amount' => 50, 'currency' => 'PHP', 'validation' => ['country' => 'PH']],
        'inputs' => ['fields' => ['documents'], 'documents' => [
            ['key' => 'identity', 'label' => 'Government ID', 'accept' => ['image/jpeg', 'image/png'], 'required' => true],
            ['key' => 'afs', 'label' => 'Financial statements', 'accept' => ['application/pdf', 'text/csv'], 'required' => false],
        ]],
        'feedback' => [], 'rider' => [], 'count' => 1, 'prefix' => 'DOC', 'mask' => '****',
        'execution' => ['schema' => 'voucher.execution.v2', 'driver' => 'default'],
    ];
}

it('round trips named claim document requirements with MIME types', function (): void {
    $payload = claimDocumentsPayload();
    $instruction = VoucherInstructionsData::createFromAttribs($payload);
    $wire = json_decode(json_encode($instruction->toCleanArray(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
    expect(data_get($wire, 'inputs.documents'))->toBe($payload['inputs']['documents'])
        ->and(data_get($wire, 'inputs.fields'))->toContain('documents');
});

it('allows several documents to accept the same MIME type', function (): void {
    $payload = claimDocumentsPayload();
    $payload['inputs']['documents'][1]['accept'] = ['image/jpeg'];
    expect(VoucherInstructionsData::createFromAttribs($payload)->inputs->documents)->toHaveCount(2);
});

it('rejects duplicate keys and unsupported document MIME types', function (array $documents): void {
    $payload = claimDocumentsPayload();
    $payload['inputs']['documents'] = $documents;
    VoucherInstructionsData::createFromAttribs($payload);
})->with([
    'duplicate keys' => [[['key' => 'id', 'label' => 'ID', 'accept' => ['image/png'], 'required' => true], ['key' => 'id', 'label' => 'Other', 'accept' => ['image/jpeg'], 'required' => true]]],
    'unknown type' => [[['key' => 'id', 'label' => 'ID', 'accept' => ['application/x-unknown'], 'required' => true]]],
])->throws(ValidationException::class);

it('rejects conflicting claim and execution document definitions', function (): void {
    $payload = claimDocumentsPayload();
    $payload['execution']['document_requirements'] = [['type' => 'identity', 'timing' => 'optional', 'requires_acceptance' => false, 'contributor' => 'claimant']];
    VoucherInstructionsData::createFromAttribs($payload);
})->throws(ValidationException::class);

it('honours the host document count limit', function (): void {
    config()->set('instructions.documents.max_items', 1);
    VoucherInstructionsData::createFromAttribs(claimDocumentsPayload());
})->throws(ValidationException::class);

it('requires document definitions when the document step is selected', function (): void {
    $payload = claimDocumentsPayload();
    $payload['inputs']['documents'] = [];
    VoucherInstructionsData::createFromAttribs($payload);
})->throws(ValidationException::class);
