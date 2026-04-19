# Redemption Contract Validation Architecture

## Overview

The redemption contract system in the voucher package is a modular validation architecture designed to separate:

- **what must be collected** from the claimant
- **what must be true** about that collected data
- **how the collected data is normalized**
- **how validation results are persisted and surfaced**

This architecture is intended to be consumed by host applications such as x-change, including UI-oriented flows like form-flow manager.

---

## Core Principle

The system is built around a strict boundary:

- `inputs.fields` = **presence contract**
- `validation.*` = **semantic contract**

This means:

- `inputs.fields` tells the host app and form-flow what must be collected
- `validation.*` tells the engine how to evaluate the collected evidence

This boundary is the most important design rule in the system.

---

## End-to-End Flow

### 1. Voucher instruction contract
A voucher is issued with instructions such as:

```php
[
    'inputs' => [
        'fields' => ['otp', 'selfie', 'location', 'kyc'],
    ],
    'validation' => [
        'otp' => [
            'required' => true,
            'on_failure' => 'block',
        ],
        'location' => [
            'required' => true,
            'target_lat' => 14.5995,
            'target_lng' => 121.0288,
            'radius_meters' => 100,
            'on_failure' => 'block',
        ],
        'face_match' => [
            'required' => true,
            'min_confidence' => 0.90,
            'on_failure' => 'block',
        ],
    ],
]
```

This is the **contract declaration**.

---

### 2. Host app / form-flow collects data
The host app renders forms based on `inputs.fields`.

The collected data is then attached to redeemer metadata, typically under:

```php
[
    'inputs' => [
        // collected data
    ],
]
```

Legacy or canonical evidence may also exist under:

```php
[
    'redemption' => [
        // normalized / legacy evidence
    ],
]
```

The voucher package supports both, with `redemption.*` taking precedence over `inputs.*`.

---

### 3. ValidateRedemptionContract pipeline runs
The `ValidateRedemptionContract` pipeline step:

- delegates validation to `RedemptionContractEngine`
- persists validation results to voucher metadata
- blocks or allows continuation based on `should_block`

---

### 4. RedemptionContractEngine extracts and evaluates evidence
The engine:

1. calls `RedemptionEvidenceExtractor`
2. normalizes redeemer metadata into `RedemptionEvidenceData`
3. runs all registered validators whose `supports()` method returns true
4. collects issues into a structured `RedemptionValidationResultData`

---

### 5. Validation result is persisted
If validation fails or warns, `ValidateRedemptionContract` persists:

```php
metadata['redemption_validation'] = [
    'passed' => false,
    'should_block' => true|false,
    'checked_at' => '...',
    'issues' => [...],
    'violations' => [...],
]
```

This makes the result auditable and available to host apps.

---

## Main Components

## 1. ValidateRedemptionContract

### Responsibility
This is the pipeline entry point for contract enforcement.

### It does
- always invokes the engine
- persists structured validation result metadata
- throws `VoucherRedemptionContractViolationException` when blocking issues exist
- allows redemption to continue when only warning-level issues exist

### Important architectural decision
This pipeline no longer depends on `validation` being present before running.

That change was critical because presence-only contracts such as:

```php
'inputs' => [
    'fields' => ['name', 'email', 'otp'],
]
```

must still be enforced even without any `validation.*` rules.

---

## 2. RedemptionContractEngine

### Responsibility
This is the central validation orchestrator.

### It does
- obtains normalized evidence from `RedemptionEvidenceExtractor`
- evaluates all registered validators
- aggregates issues
- computes:
  - `passed`
  - `should_block`
  - `issues`
  - `checked_at`

### Key property
The engine is the single source of truth for contract evaluation.

The pipeline should not contain validation logic directly.

---

## 3. RedemptionEvidenceExtractor

### Responsibility
Transforms redeemer metadata into a normalized `RedemptionEvidenceData` object.

### Why it exists
Host apps do not always submit the same payload shape.

The extractor bridges:

- older canonical voucher shapes under `redemption.*`
- form-flow collected payloads under `inputs.*`

### Current supported evidence sources

#### Generic fields
The extractor supports:
- `name`
- `email`
- `birth_date`
- `address`
- `mobile`
- `reference_code`
- `gross_monthly_income`

from:
- `redemption.<field>`
- `inputs.<field>`

#### Signature
Supports:
- `redemption.signature`
- `inputs.signature`

#### Selfie
Supports:
- `redemption.selfie`
- `inputs.selfie`

#### Location
Supports:
- `redemption.location.lat`
- `redemption.location.lng`
- `inputs.location.lat`
- `inputs.location.lng`
- `inputs.latitude`
- `inputs.longitude`

#### OTP
Supports canonical and form-flow-oriented variants:

- `redemption.otp.value`
- `redemption.otp`
- `redemption.otp.verified`
- `redemption.otp.verified_at`
- `redemption.otp_verified`
- `redemption.otp_verified_at`

- `inputs.otp` when scalar
- `inputs.otp.value`
- `inputs.otp.otp_code`
- `inputs.otp_code`
- `inputs.otp.verified`
- `inputs.otp_verified`
- `inputs.otp.verified_at`
- `inputs.verified_at`

It also infers `otp_verified = true` when `verified_at` exists and no explicit false flag is present.

#### KYC
Supports:
- `redemption.kyc`
- `inputs.kyc`

and also flat KYC handler output via synthesized KYC payload built from:
- `transaction_id`
- `status`
- `name`
- `date_of_birth`
- `address`
- `id_number`
- `id_type`
- `nationality`
- `id_card_full`
- `id_card_cropped`
- `selfie`

### Important rule
The extractor gives precedence to `redemption.*` over `inputs.*`.

That preserves backward compatibility while supporting form-flow-style payloads.

---

## 4. Validators

Each validator implements a focused rule set and returns structured issues.

### Registered validators
Typical order:

1. `RequiredInputFieldsValidator`
2. `SignatureRuleValidator`
3. `SelfieRuleValidator`
4. `LocationRuleValidator`
5. `OtpRuleValidator`
6. `TimeRuleValidator`
7. `FaceMatchRuleValidator`

### Why order matters
Presence failures should surface before semantic failures where possible.

---

## Presence vs Semantic Validators

## RequiredInputFieldsValidator
### Responsibility
Enforces `inputs.fields`.

### Examples
- `signature` must be present
- `selfie` must be present
- `otp` must be present
- `location` must contain both lat/lng
- `kyc` must exist as a payload

### Important behavior
This validator owns **presence checks**.

It should be the first place that reports missing required inputs.

---

## Semantic validators
These enforce meaning and quality, not mere existence.

### OtpRuleValidator
Checks whether OTP is verified when `validation.otp.required = true`.

### LocationRuleValidator
Checks geofence/radius behavior when `validation.location` is present.

### TimeRuleValidator
Checks allowed time windows and duration limits.

### FaceMatchRuleValidator
Checks face verification semantics only when explicitly configured via `validation.face_match`.

### Important architectural decision
`FaceMatchRuleValidator` must **not** auto-run simply because `kyc` appears in `inputs.fields`.

That coupling was intentionally removed.

Reason:
- `inputs.fields = ['kyc']` means KYC presence is required
- `validation.face_match` means face-match semantics are required

These must remain separate.

---

## Data Objects

## RedemptionEvidenceData
Normalized evidence DTO consumed by validators.

Contains fields such as:
- `signature`
- `selfie`
- `latitude`
- `longitude`
- `otp`
- `otp_verified`
- `otp_verified_at`
- `kyc`
- `face_verification_verified`
- `face_match`
- `match_confidence`
- `face_verified_at`
- `face_failure_reason`

and additional generic fields like:
- `name`
- `email`
- `birth_date`
- `address`
- `mobile`
- `reference_code`
- `gross_monthly_income`

---

## RedemptionValidationIssueData
Represents one issue:
- `field`
- `code`
- `severity`
- `message`
- `context`

---

## RedemptionValidationResultData
Represents the full engine result:
- `passed`
- `should_block`
- `issues`
- `checked_at`

---

## Current Contract Model for Host Apps

## Layer 1 — Voucher instruction contract
Used by issuance and form rendering:

```php
'inputs' => [
    'fields' => ['otp', 'selfie', 'location', 'kyc'],
],
'validation' => [
    'otp' => ['required' => true],
    'face_match' => ['required' => true],
]
```

---

## Layer 2 — Collected data payload
Used by the host app after user/device collection, typically under `inputs.*`.

Examples:

### Generic
```php
'inputs' => [
    'name' => 'Juan Dela Cruz',
    'email' => 'juan@example.com',
]
```

### OTP step output
```php
'inputs' => [
    'otp' => [
        'otp_code' => '123456',
        'verified_at' => '2026-04-19T10:30:00+08:00',
        'reference_id' => 'flow-abc123',
    ],
]
```

### Signature step output
```php
'inputs' => [
    'signature' => 'data:image/png;base64,...',
    'width' => 600,
    'height' => 256,
    'format' => 'image/png',
    'timestamp' => '2026-04-19T12:00:00+08:00',
]
```

### Location step output
```php
'inputs' => [
    'latitude' => 14.5995,
    'longitude' => 121.0288,
    'formatted_address' => '...',
]
```

### Flat KYC step output
```php
'inputs' => [
    'transaction_id' => 'MOCK-KYC-123',
    'status' => 'approved',
    'id_number' => 'ABC123456',
]
```

---

## Layer 3 — Normalized evidence
Produced by `RedemptionEvidenceExtractor` and consumed by validators.

---

## Architectural Benefits

This architecture gives the package and host apps:

- explicit contract semantics
- support for both legacy and form-flow payloads
- strong testability
- reduced coupling between collection and evaluation
- auditable validation history
- pluggable extensibility for future validators

---

## Guidance for x-change AI agent

When integrating with the voucher package:

1. treat `inputs.fields` as the list of required collected fields
2. treat `validation.*` as explicit semantic rule declarations
3. store collected form-flow output under `inputs.*`
4. rely on voucher package normalization where possible
5. do not assume `kyc` implies face match
6. only enforce face-match semantics when `validation.face_match` is present

This distinction is critical for correct behavior.
