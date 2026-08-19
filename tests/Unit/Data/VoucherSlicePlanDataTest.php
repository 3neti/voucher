<?php

declare(strict_types=1);

use LBHurtado\Voucher\Data\VoucherInstructionsData;
use LBHurtado\Voucher\Data\VoucherSlicePlanData;
use LBHurtado\Voucher\Enums\VoucherSlicePlanMode;
use LBHurtado\Voucher\Enums\VoucherSliceSelectionPolicy;
use LBHurtado\Voucher\Exceptions\UnsupportedVoucherSlicePlanSchemaException;
use LBHurtado\Voucher\Services\VoucherSlicePlanFactory;

it('builds equal plans with stable default labels and deterministic minor-unit reconciliation', function () {
    $plan = app(VoucherSlicePlanFactory::class)->equal(
        totalMinor: 100,
        currency: 'php',
        count: 3,
    );

    expect($plan->schema)->toBe('voucher.slice-plan.v1')
        ->and($plan->mode)->toBe(VoucherSlicePlanMode::Equal)
        ->and($plan->selection)->toBe(VoucherSliceSelectionPolicy::NextOnly)
        ->and($plan->currency)->toBe('PHP')
        ->and($plan->slices->toCollection()->pluck('id')->all())->toBe(['slice_1', 'slice_2', 'slice_3'])
        ->and($plan->slices->toCollection()->pluck('label')->all())->toBe(['Slice 1', 'Slice 2', 'Slice 3'])
        ->and($plan->slices->toCollection()->pluck('amount_minor')->all())->toBe([34, 33, 33])
        ->and($plan->slices->toCollection()->sum('amount_minor'))->toBe(100);
});

it('allows presentation labels without making them execution identity', function () {
    $plan = app(VoucherSlicePlanFactory::class)->equal(
        totalMinor: 10_000,
        currency: 'PHP',
        count: 2,
        labels: ['Morning Fare', 'Evening Fare'],
    );

    expect($plan->slices->toCollection()->pluck('id')->all())->toBe(['slice_1', 'slice_2'])
        ->and($plan->slices->toCollection()->pluck('label')->all())->toBe(['Morning Fare', 'Evening Fare'])
        ->and($plan->slices->toCollection()->pluck('amount_minor')->all())->toBe([5_000, 5_000]);
});

it('builds scheduled plans with explicit windows and selection policy', function () {
    $plan = app(VoucherSlicePlanFactory::class)->scheduled(
        totalMinor: 10_000,
        currency: 'PHP',
        slices: [
            [
                'id' => 'school_fare',
                'label' => 'School Fare',
                'amount_minor' => 4_000,
                'claim_on' => '2026-08-20T00:00:00+08:00',
            ],
            [
                'id' => 'meal_allowance',
                'description' => 'Meal Allowance',
                'amount_minor' => 6_000,
                'claim_by' => '2026-08-31T23:59:59+08:00',
            ],
        ],
        selection: VoucherSliceSelectionPolicy::One,
    );

    expect($plan->mode)->toBe(VoucherSlicePlanMode::Scheduled)
        ->and($plan->selection)->toBe(VoucherSliceSelectionPolicy::One)
        ->and($plan->slices->toCollection()->pluck('label')->all())->toBe(['School Fare', 'Meal Allowance'])
        ->and($plan->slices->toCollection()->pluck('sequence')->all())->toBe([1, 2]);
});

it('builds flexible plans without inventing future slice rows', function () {
    $plan = app(VoucherSlicePlanFactory::class)->flexible(
        totalMinor: 100_000,
        currency: 'PHP',
        maxSlices: 20,
        minAmountMinor: 1_500,
    );

    expect($plan->mode)->toBe(VoucherSlicePlanMode::Flexible)
        ->and($plan->selection)->toBe(VoucherSliceSelectionPolicy::FlexibleAmount)
        ->and($plan->slices)->toHaveCount(0)
        ->and($plan->max_slices)->toBe(20)
        ->and($plan->min_amount_minor)->toBe(1_500);
});

it('hydrates the canonical plan as part of voucher instructions', function () {
    $plan = app(VoucherSlicePlanFactory::class)->equal(10_000, 'PHP', 2);

    $instructions = VoucherInstructionsData::from([
        ...validVoucherInstructions(100)->toArray(),
        'slice_plan' => $plan->canonicalArray(),
    ]);

    expect($instructions->slice_plan)->toBeInstanceOf(VoucherSlicePlanData::class)
        ->and($instructions->slice_plan?->hash())->toBe($plan->hash())
        ->and(data_get($instructions->toCleanArray(), 'slice_plan.slices.0.label'))->toBe('Slice 1');
});

it('hashes semantically identical plans independently of supplied row order', function () {
    $ordered = VoucherSlicePlanData::from([
        'schema' => VoucherSlicePlanData::SCHEMA,
        'mode' => 'scheduled',
        'selection' => 'one_or_many',
        'total_minor' => 10_000,
        'currency' => 'PHP',
        'slices' => [
            ['id' => 'slice_1', 'label' => 'First', 'amount_minor' => 4_000, 'sequence' => 1],
            ['id' => 'slice_2', 'label' => 'Second', 'amount_minor' => 6_000, 'sequence' => 2],
        ],
    ]);
    $reversed = VoucherSlicePlanData::from([
        ...$ordered->canonicalArray(),
        'slices' => array_reverse($ordered->canonicalArray()['slices']),
    ]);

    expect($reversed->hash())->toBe($ordered->hash());
});

it('rejects non-conserving scheduled plans', function () {
    app(VoucherSlicePlanFactory::class)->scheduled(
        totalMinor: 10_000,
        currency: 'PHP',
        slices: [
            ['amount_minor' => 5_000],
            ['amount_minor' => 5_001],
        ],
    );
})->throws(InvalidArgumentException::class, 'Voucher slice amounts must exactly equal the plan total.');

it('rejects duplicate execution identities and non-contiguous ordering', function (array $slices, string $message) {
    VoucherSlicePlanData::from([
        'schema' => VoucherSlicePlanData::SCHEMA,
        'mode' => 'scheduled',
        'selection' => 'one',
        'total_minor' => 10_000,
        'currency' => 'PHP',
        'slices' => $slices,
    ]);
})->with([
    'duplicate identity' => [[
        ['id' => 'fare', 'label' => 'First', 'amount_minor' => 5_000, 'sequence' => 1],
        ['id' => 'fare', 'label' => 'Second', 'amount_minor' => 5_000, 'sequence' => 2],
    ], 'Voucher slice plan IDs must be unique.'],
    'sequence gap' => [[
        ['id' => 'fare_1', 'label' => 'First', 'amount_minor' => 5_000, 'sequence' => 1],
        ['id' => 'fare_2', 'label' => 'Second', 'amount_minor' => 5_000, 'sequence' => 3],
    ], 'Voucher slice plan sequences must be contiguous from one.'],
])->throws(InvalidArgumentException::class);

it('rejects invalid mode-specific selection policies and capacity', function (array $payload, string $message) {
    VoucherSlicePlanData::from([
        'schema' => VoucherSlicePlanData::SCHEMA,
        'total_minor' => 10_000,
        'currency' => 'PHP',
        ...$payload,
    ]);
})->with([
    'equal selection' => [[
        'mode' => 'equal',
        'selection' => 'one',
        'slices' => [
            ['id' => 'slice_1', 'label' => 'Slice 1', 'amount_minor' => 5_000, 'sequence' => 1],
            ['id' => 'slice_2', 'label' => 'Slice 2', 'amount_minor' => 5_000, 'sequence' => 2],
        ],
    ], 'Equal slice plans require the next_only selection policy.'],
    'flexible capacity exceeds total' => [[
        'mode' => 'flexible',
        'selection' => 'flexible_amount',
        'slices' => [],
        'max_slices' => 2,
        'min_amount_minor' => 10_001,
    ], 'Flexible slice plan capacity must fit within the plan total.'],
])->throws(InvalidArgumentException::class);

it('rejects canonical and retired slice fields in the same instructions', function () {
    $plan = app(VoucherSlicePlanFactory::class)->equal(10_000, 'PHP', 2);
    $payload = validVoucherInstructions(100)->toArray();
    data_set($payload, 'cash.slice_mode', 'fixed');
    data_set($payload, 'cash.slices', 2);
    data_set($payload, 'slice_plan', $plan->canonicalArray());

    VoucherInstructionsData::from($payload);
})->throws(InvalidArgumentException::class, 'Canonical voucher slice plans cannot be combined with retired cash slice fields.');

it('rejects slice plans that disagree with the cash amount or currency', function (string $currency, int $totalMinor, string $message) {
    $plan = app(VoucherSlicePlanFactory::class)->equal($totalMinor, $currency, 2);
    $payload = validVoucherInstructions(100)->toArray();
    data_set($payload, 'slice_plan', $plan->canonicalArray());

    VoucherInstructionsData::from($payload);
})->with([
    'amount mismatch' => ['PHP', 9_000, 'Voucher slice plan total must exactly equal the cash instruction amount.'],
    'currency mismatch' => ['USD', 10_000, 'Voucher slice plan currency must match the cash instruction currency.'],
])->throws(InvalidArgumentException::class);

it('rejects unsupported slice plan schemas', function () {
    VoucherSlicePlanData::from([
        'schema' => 'voucher.slice-plan.v2',
        'mode' => 'equal',
        'selection' => 'next_only',
        'total_minor' => 10_000,
        'currency' => 'PHP',
        'slices' => [
            ['id' => 'slice_1', 'label' => 'Slice 1', 'amount_minor' => 5_000, 'sequence' => 1],
            ['id' => 'slice_2', 'label' => 'Slice 2', 'amount_minor' => 5_000, 'sequence' => 2],
        ],
    ]);
})->throws(UnsupportedVoucherSlicePlanSchemaException::class);

it('keeps unsliced instruction serialization unchanged', function () {
    $instructions = validVoucherInstructions(100);

    expect($instructions->slice_plan)->toBeNull()
        ->and($instructions->toCleanArray())->not->toHaveKey('slice_plan');
});
