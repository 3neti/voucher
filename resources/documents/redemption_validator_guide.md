# How to Add a New Redemption Validator

## Purpose

This guide explains how to add a new validator to the redemption contract system in a way that is consistent with the current architecture and contract model.

This guide is intended for voucher package maintainers and host-app AI agents.

---

## First Principle: Decide What Kind of Rule You Are Adding

Before writing code, decide whether your feature is:

### A presence requirement
Example:
- document must be submitted
- selfie must be submitted
- otp must be submitted

If yes, it belongs under:

```php
'inputs' => [
    'fields' => [...],
]
```

and is usually enforced by `RequiredInputFieldsValidator`.

### A semantic requirement
Example:
- OTP must be verified
- location must be within radius
- face match must pass
- time limit must not be exceeded

If yes, it belongs under:

```php
'validation' => [
    ...
]
```

and should be implemented as a dedicated validator.

This distinction is mandatory.

---

## Architecture Recap

Validation flows through:

1. `ValidateRedemptionContract`
2. `RedemptionContractEngine`
3. `RedemptionEvidenceExtractor`
4. registered validators

Each validator:
- implements `RedemptionRuleValidator`
- advertises applicability via `supports()`
- returns issues via `validate()`

---

## Step 1: Create the validator class

Example:

```php
namespace LBHurtado\Voucher\Validators;

use LBHurtado\Voucher\Contracts\RedemptionRuleValidator;
use LBHurtado\Voucher\Data\RedemptionEvidenceData;
use LBHurtado\Voucher\Models\Voucher;

class OtpRuleValidator implements RedemptionRuleValidator
{
    public function supports(Voucher $voucher): bool
    {
        return (bool) $voucher->instructions?->validation?->otp?->required;
    }

    public function validate(Voucher $voucher, RedemptionEvidenceData $evidence): array
    {
        // return array of RedemptionValidationIssueData
    }
}
```

---

## Step 2: Implement `supports()` correctly

This is one of the most important design decisions.

### Rule
`supports()` should be driven by **explicit semantic configuration**, not by `inputs.fields`.

### Correct
```php
return (bool) $voucher->instructions?->validation?->otp?->required;
```

### Incorrect
```php
return collect($voucher->instructions?->inputs?->fields ?? [])->contains('otp');
```

Why incorrect:
- `inputs.fields` is presence-only
- semantic validators must not auto-run just because a field is collected

### Special note on KYC and FaceMatch
This was a real bug that has already been corrected.

Do **not** write validators so that:
- requiring `kyc` input automatically triggers face-match validation

Instead:
- `kyc` in `inputs.fields` means KYC presence required
- `validation.face_match` means face-match semantics required

---

## Step 3: Implement `validate()`

A validator receives:
- `Voucher $voucher`
- `RedemptionEvidenceData $evidence`

It should return an array of `RedemptionValidationIssueData`.

Example shape:

```php
return [
    new RedemptionValidationIssueData(
        field: 'otp',
        code: RedemptionValidationCode::OTP_NOT_VERIFIED,
        severity: RedemptionValidationSeverity::BLOCK,
        message: 'Required OTP verification is missing.',
        context: [
            'otp' => $evidence->otp,
            'otp_verified' => $evidence->otp_verified,
        ],
    ),
];
```

---

## Step 4: Add or update instruction DTOs

If your validator needs new voucher instruction options, add them to the instruction data objects.

Examples:
- `OtpValidationInstructionData`
- `FaceMatchValidationInstructionData`
- `TimeValidationInstructionData`
- `LocationValidationInstructionData`

Also update:
- `ValidationInstructionData`
- `VoucherInstructionsData::rules()`
- `VoucherInstructionsData::createFromAttribs()`
- optionally defaults / generation helpers

---

## Step 5: Extend `RedemptionEvidenceData` if needed

If your validator requires new normalized evidence, add it to `RedemptionEvidenceData`.

Examples:
- `otp_verified`
- `match_confidence`
- `latitude`
- `longitude`

Be deliberate here: only add fields that belong in normalized validation evidence.

---

## Step 6: Update `RedemptionEvidenceExtractor` if needed

If the host app may submit evidence in multiple shapes, normalize it here.

The extractor currently supports:
- canonical `redemption.*` shapes
- form-flow-oriented `inputs.*` shapes
- selected flat handler output patterns

### Current examples
- nested and flat OTP payloads
- signature/selfie via `inputs.*`
- location via nested or flat lat/lng
- nested or flat-ish KYC presence payloads

### Rule of precedence
Prefer:
1. `redemption.*`
2. `inputs.*`

This preserves backward compatibility.

### Important note
Do not invent semantic meaning in the extractor unless justified.

Example:
- flat KYC presence payload can satisfy `kyc` presence
- but flat KYC payload should not automatically imply face-match success

---

## Step 7: Register the validator

In `VoucherServiceProvider`:

```php
$this->app->singleton(MyNewRuleValidator::class);

$this->app->singleton(RedemptionContractEngine::class, function ($app) {
    return new RedemptionContractEngine(
        extractor: $app->make(RedemptionEvidenceExtractor::class),
        validators: [
            $app->make(RequiredInputFieldsValidator::class),
            $app->make(SignatureRuleValidator::class),
            $app->make(SelfieRuleValidator::class),
            $app->make(LocationRuleValidator::class),
            $app->make(OtpRuleValidator::class),
            $app->make(TimeRuleValidator::class),
            $app->make(FaceMatchRuleValidator::class),
            $app->make(MyNewRuleValidator::class),
        ],
    );
});
```

Choose validator order carefully.

### Recommended ordering rule
- presence first
- simple semantics next
- richer semantics later

---

## Step 8: Write tests

Every validator should have at least:

### 1. Unit tests for the validator
Examples:
- supports correct vouchers
- ignores irrelevant vouchers
- produces correct issue codes
- respects warn/block severity
- handles edge cases

### 2. Extractor tests if new evidence shapes are supported
Examples:
- reads from `inputs.*`
- reads from `redemption.*`
- prefers redemption over inputs
- supports realistic form-handler outputs

### 3. Integration tests through `ValidateRedemptionContract`
Examples:
- passes when evidence is valid
- blocks when evidence is invalid
- persists expected violation metadata
- supports form-flow payload shape

---

## Step 9: Preserve the contract boundary

When adding validators, always ask:

### Is this a presence check?
Then it should not become a semantic validator.

### Is this a semantic rule?
Then it should not auto-run from `inputs.fields`.

This is the single biggest source of confusion and accidental coupling.

---

## Worked Example: FaceMatch

### Wrong design
- `inputs.fields = ['kyc']`
- automatically run `FaceMatchRuleValidator`

Problem:
- flat KYC handler payload satisfies presence
- but does not include face-match semantics
- valid KYC presence would be falsely rejected

### Correct design
- `inputs.fields = ['kyc']` → KYC payload must exist
- `validation.face_match.required = true` → face-match semantics must pass

That is the pattern to follow for future validators too.

---

## Worked Example: OTP

### Presence
`inputs.fields = ['otp']`
means OTP must be submitted

### Semantics
`validation.otp.required = true`
means OTP must be verified

### Extractor support
The extractor now supports:
- `inputs.otp`
- `inputs.otp.value`
- `inputs.otp.otp_code`
- `inputs.otp_code`
- `inputs.otp.verified`
- `inputs.otp_verified`
- `inputs.otp.verified_at`
- `inputs.verified_at`

It also infers verification from `verified_at` when appropriate.

This is a good example of how collection shapes and semantic rules remain separate.

---

## Final Checklist

Before considering a validator complete, verify:

- [ ] It implements `RedemptionRuleValidator`
- [ ] `supports()` is based on semantic config, not collection presence
- [ ] `validate()` returns structured issues
- [ ] Any new DTO/config fields are added
- [ ] Any new evidence shapes are normalized in extractor
- [ ] Service provider registration is updated
- [ ] Unit tests are added
- [ ] Integration tests through `ValidateRedemptionContract` are added
- [ ] Presence and semantics remain cleanly separated

---

## Summary

A validator is correct when it:

- is explicit
- is pluggable
- is test-covered
- respects the contract boundary
- does not rely on hidden implications

That is the current standard for the redemption contract system.
