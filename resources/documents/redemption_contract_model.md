# Redemption Contract Model

## Overview

The voucher package uses a two-layer redemption contract model.

- `inputs.fields` = **presence contract**
- `validation.*` = **semantic contract**

This model is intentionally explicit and should remain stable across future package and host-app changes.

---

## 1. Presence Contract

### Definition
`inputs.fields` declares what must be collected from the claimant.

Example:

```php
'inputs' => [
    'fields' => ['name', 'email', 'otp', 'selfie', 'location', 'kyc'],
],
```

This means the claimant must provide these fields before redemption can succeed.

### Presence is enforced by
`RequiredInputFieldsValidator`

### Presence means existence, not validity
Examples:

- `name` exists
- `email` exists
- `otp` exists
- `signature` exists
- `selfie` exists
- `location` contains both latitude and longitude
- `kyc` exists as a non-empty payload

Presence does not imply:
- OTP is verified
- location is within radius
- face match passed

---

## 2. Semantic Contract

### Definition
`validation.*` declares what must be true about submitted evidence.

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
        'target_lng' => 121.0288,
        'radius_meters' => 100,
        'on_failure' => 'block',
    ],
    'face_match' => [
        'required' => true,
        'min_confidence' => 0.90,
        'on_failure' => 'block',
    ],
]
```

These rules are semantic because they evaluate meaning and quality, not mere existence.

---

## 3. Canonical Interpretation Rules

## `inputs.fields`
Means:
> the field must be collected and present

## `validation.*`
Means:
> explicit semantic rules must be enforced

This means:

### OTP
- `inputs.fields = ['otp']` → OTP must be submitted
- `validation.otp.required = true` → OTP must be verified

### Location
- `inputs.fields = ['location']` → coordinates must be submitted
- `validation.location` → coordinates must satisfy radius rules

### KYC
- `inputs.fields = ['kyc']` → KYC payload must be present
- `validation.face_match` → face-match semantics must pass

This separation is now intentional and enforced by tests.

---

## 4. Important Non-Implications

These are especially important for host app integrations.

### KYC does not imply face match
This is the most important recent refinement.

If a voucher requires:

```php
'inputs' => [
    'fields' => ['kyc'],
],
```

that does **not** imply face-match validation should run.

Why:
- KYC presence can be satisfied by flat KYC handler output
- flat KYC handler output does not necessarily contain face-match semantics

Face-match must only run when explicitly declared:

```php
'validation' => [
    'face_match' => [
        'required' => true,
    ],
]
```

---

## 5. Accepted Collected Data Shapes

The package now supports both canonical and form-flow-oriented collected data.

## Generic field examples
```php
'inputs' => [
    'name' => 'Juan Dela Cruz',
    'email' => 'juan@example.com',
    'birth_date' => '1990-01-01',
]
```

## OTP examples
### scalar
```php
'inputs' => [
    'otp' => '123456',
]
```

### nested form-flow OTP step
```php
'inputs' => [
    'otp' => [
        'otp_code' => '123456',
        'verified_at' => '2026-04-19T10:30:00+08:00',
        'reference_id' => 'flow-abc123',
    ],
]
```

### flat OTP transform
```php
'inputs' => [
    'otp_code' => '123456',
    'verified_at' => '2026-04-19T10:30:00+08:00',
]
```

## Signature
```php
'inputs' => [
    'signature' => 'data:image/png;base64,...',
]
```

## Selfie
```php
'inputs' => [
    'selfie' => 'data:image/jpeg;base64,...',
]
```

## Location
### nested
```php
'inputs' => [
    'location' => [
        'lat' => 14.5995,
        'lng' => 121.0288,
    ],
]
```

### flat handler output
```php
'inputs' => [
    'latitude' => 14.5995,
    'longitude' => 121.0288,
]
```

## KYC
### nested
```php
'inputs' => [
    'kyc' => [
        'face_verification' => [
            'verified' => true,
            'face_match' => true,
            'match_confidence' => 0.95,
        ],
    ],
]
```

### flat handler output
```php
'inputs' => [
    'transaction_id' => 'MOCK-KYC-123',
    'status' => 'approved',
    'id_number' => 'ABC123456',
    'id_type' => 'National ID',
]
```

---

## 6. Presence Rules in Practice

### Required generic fields
These pass when non-empty values are present:
- `name`
- `email`
- `birth_date`
- `address`
- `mobile`
- `reference_code`
- `gross_monthly_income`

### Required structured fields
These pass when evidence is present:
- `signature`
- `selfie`
- `otp`
- `location`
- `kyc`

### Edge-case rules already enforced
- empty strings do not count as present
- partial location payloads do not count as present
- empty KYC arrays do not count as present

---

## 7. Semantic Rules in Practice

### OTP verification
OTP may be present but still fail semantic verification if:
- explicit `verified = false`
- no verification metadata exists when `validation.otp.required = true`

The extractor also supports inferred verification from `verified_at` when no explicit flag is given.

### Location radius
Location may be present but still fail semantic validation if coordinates are outside the allowed radius.

### Face match
KYC may be present but face match may still fail if:
- verification is false
- face_match is false
- confidence is below threshold

### Time validation
Redemption may fail when:
- outside allowed time window
- configured duration limit is exceeded

---

## 8. Invariant

The key invariant is:

> Every field declared in `inputs.fields` must be present in submitted redemption evidence before redemption can succeed.

This remains true even when no `validation.*` block is defined.

---

## 9. Guidance for x-change and other host apps

When building claim payloads:

### Use `inputs.*` for collected step data
This aligns naturally with form-flow manager and current extractor support.

### Use `validation.*` only for explicit semantics
Do not rely on implied validation behavior.

### Do not assume `kyc` means face match
Treat them as separate layers.

### Allow the voucher package to normalize evidence
The extractor is designed to bridge shape differences.

---

## 10. Summary

The final mental model is:

- **Collection requirement** → `inputs.fields`
- **Evaluation requirement** → `validation.*`

That distinction is now central to the package architecture and should guide all future host-app integrations.
