# Redemption Contract Model

## Overview

The voucher package defines redemption requirements in two distinct layers:

- `inputs.fields` → **presence contract**
- `validation.*` → **semantic contract**

This separation keeps the redemption pipeline predictable, extensible, and easy to reason about.

---

## 1. Presence Contract: `inputs.fields`

`inputs.fields` declares which inputs must be **submitted** before redemption can succeed.

Examples:

```php
'inputs' => [
    'fields' => ['signature', 'selfie', 'otp', 'location'],
],
```

This means the redeemer must provide:

- a signature
- a selfie
- an OTP value
- a location payload

These checks are enforced by the `RequiredInputFieldsValidator`.

### What presence means

- `signature` → non-empty signature value exists
- `selfie` → non-empty selfie value exists
- `otp` → OTP input value exists
- `location` → both latitude and longitude exist
- `kyc` → KYC payload exists
- `reference_code` → reference code exists
- `mobile` → mobile exists
- `email` → email exists
- `name` → name exists
- `address` → address exists
- `birth_date` → birth date exists
- `gross_monthly_income` → income value exists

Presence checks do **not** determine whether an input is valid beyond existence.

---

## 2. Semantic Contract: `validation.*`

`validation.*` declares what must be **true** about submitted inputs or redemption context.

Examples:

```php
'validation' => [
    'otp' => [
        'required' => true,
        'on_failure' => 'block',
    ],
    'location' => [
        'required' => true,
        'target_lat' => 14.5995,
        'target_lng' => 120.9842,
        'radius_meters' => 100,
        'on_failure' => 'block',
    ],
    'face_match' => [
        'required' => true,
        'min_confidence' => 0.90,
        'on_failure' => 'block',
    ],
],
```

Semantic validators enforce rules such as:

- OTP must be verified
- location must be within radius
- redemption must occur within a time window
- face match must pass
- face match confidence must exceed a threshold

These checks are enforced by pluggable validators such as:

- `OtpRuleValidator`
- `LocationRuleValidator`
- `TimeRuleValidator`
- `FaceMatchRuleValidator`

---

## 3. Key Rule of Thumb

### `inputs.fields`
Declares **what must be submitted**

### `validation.*`
Declares **what must be true about what was submitted**

This boundary should remain stable across future validators.

---

## 4. Examples

### Example A: OTP is required and must be verified

```php
'inputs' => [
    'fields' => ['otp'],
],
'validation' => [
    'otp' => [
        'required' => true,
        'on_failure' => 'block',
    ],
],
```

Interpretation:

1. The redeemer must submit an OTP value
2. The submitted OTP must also be verified

### Example B: Selfie is required and face match must pass

```php
'inputs' => [
    'fields' => ['selfie', 'kyc'],
],
'validation' => [
    'face_match' => [
        'required' => true,
        'min_confidence' => 0.90,
        'on_failure' => 'block',
    ],
],
```

Interpretation:

1. The redeemer must submit a selfie
2. The redeemer must provide KYC evidence
3. The face verification result must pass
4. Confidence must meet the declared threshold

### Example C: Location is required, then geofence is enforced

```php
'inputs' => [
    'fields' => ['location'],
],
'validation' => [
    'location' => [
        'required' => true,
        'target_lat' => 14.5995,
        'target_lng' => 120.9842,
        'radius_meters' => 100,
        'on_failure' => 'block',
    ],
],
```

Interpretation:

1. The redeemer must submit a location
2. The location must be inside the allowed radius

---

## 5. Architecture

The redemption contract is enforced through:

1. `ValidateRedemptionContract` pipeline step
2. `RedemptionContractEngine`
3. `RedemptionEvidenceExtractor`
4. registered validators

### Validator order

A typical validator order is:

1. `RequiredInputFieldsValidator`
2. `SignatureRuleValidator`
3. `SelfieRuleValidator`
4. `LocationRuleValidator`
5. `OtpRuleValidator`
6. `TimeRuleValidator`
7. `FaceMatchRuleValidator`

This ordering allows basic presence failures to surface before deeper semantic failures.

---

## 6. Why this model is useful

This contract model gives the package:

- a clear separation of concerns
- consistent redemption behavior
- simpler debugging
- easier extension for future validators
- stable semantics for API consumers and host apps

---

## 7. Guidance for future validators

When adding a new validator, decide first:

### Is this a presence requirement?
Then it belongs in `inputs.fields`

### Is this a rule about submitted evidence?
Then it belongs in `validation.*`

Examples:

- “document must be submitted” → `inputs.fields`
- “document must be approved” → `validation.document_*`
- “otp must be submitted” → `inputs.fields`
- “otp must be verified” → `validation.otp`
- “selfie must be submitted” → `inputs.fields`
- “face match must pass” → `validation.face_match`

---

## 8. Recommended invariant

Every field in `inputs.fields` must be present in submitted redemption evidence before redemption can succeed.

This invariant should remain true even when no `validation.*` block is declared.

---

## 9. Summary

The redemption contract model is:

- **Presence** → `inputs.fields`
- **Semantics** → `validation.*`

That is the core rule. Everything else should build on top of it.
