# Digital Voucher System with Cash Entity

## Overview

The **Digital Voucher System** is designed to manage and redeem vouchers that may include monetary value (cash), specific validation rules, feedback mechanisms, and instructions for issuing or redeeming vouchers. This system includes robust handling for **cash entities** that are tightly integrated with **redeemable vouchers**.

This document outlines the `Cash` entity, its relationship to the voucher system, and its critical features like tagging, status management, redemption rules, and validation.

---

## Core Components

1. **Cash Entity (`Cash` Model)**:
    - Represents a monetary value or cash amount tied to a voucher.
    - Features secure handling with hashed secrets for redemption.
    - Enables tagging, tracking statuses, and metadata storage.
    - Supports advanced validation, including expiry and custom rules.

2. **Voucher Entity (`Voucher` Model)**:
    - Represents the actual voucher, which may reference associated `Cash`.
    - Includes instructions for issuing or redeeming vouchers (e.g., feedback or rider instructions).

---

## Features of the `Cash` Model

### 1. **Attributes**
- `amount`: Represents the monetary value in minor units using the [Brick\Money](https://brick.money) library.
- `currency`: Default to **PHP** but configurable on creation.
- `meta`: Stores custom metadata (`ArrayObject`).
- `secret`: A **hashed value** used for secure redemption.
- `expires_on`: Specifies when the cash expires.
- `status`: Tracks the current status of the cash (`e.g., MINTED, EXPIRED`).
- `reference`: A morph-to relationship to link cash to a voucher or any reference.

### 2. **Status Management**
- Uses the Spatie `HasStatuses` trait for dynamic status management.
- Allows status tracking with reasons for changes.
- Provides methods like:
    - `setStatus(CashStatus $status, string $reason = null)`
    - `hasStatus(CashStatus $status)`
    - `hasHadStatus(CashStatus $status)`
    - `getCurrentStatus()`

### 3. **Tagging**
- Uses the Spatie `HasTags` trait for adding tags to cash.
- Supports:
    - Attaching/detaching tags.
    - Querying models by tags.
    - Setting translated tags.

### 4. **Secure Redemption**
- Each cash entity includes a **hashed secret** for redemption validation:
    - Use `setSecretAttribute` to hash the secret before saving.
    - Validate with `verifySecret(string $providedSecret)`.
- Combines expiration and secret-checking for redemption with `canRedeem()`.

### 5. **Validation and Metadata**
- Custom validation rules are supported via `CashValidationRulesData` (e.g., location, country, and mobile validations).
- Metadata extends flexibility, allowing custom annotations when linking to vouchers.

### 6. **Integration with Vouchers**
- A `Cash` instance can reference a voucher via polymorphic relationships.
- Voucher instructions include:
    - `cash` validations and amounts.
    - Feedback and rider instructions.

---

## How It Works

### **Cash Creation**
A `Cash` instance typically gets created and linked to a voucher:

```php
use LBHurtado\Voucher\Models\Cash;
use Brick\Money\Money;

$cash = Cash::create([
    'amount' => Money::of(1000, 'PHP'), // 1,000 PHP in major units
    'currency' => 'PHP',
    'meta' => ['usage' => 'transport allowance'],
    'expires_on' => now()->addWeek(),
    'secret' => 'secure-secret-key', // Automatically hashed
]);
```

### **Redemption Validation**
The `canRedeem()` method ensures that the cash:
1. Has not expired.
2. Matches the provided secret:

```php
if ($cash->canRedeem($providedSecret)) {
    // Process redemption logic here
} else {
    // Handle failure (e.g., invalid secret or expired cash)
}
```

### **Status Tracking**
Handle status changes such as `MINTED`, `EXPIRED`, or `DISBURSED`:

```php
use LBHurtado\Voucher\Enums\CashStatus;

$cash->setStatus(CashStatus::DISBURSED, 'Funds disbursed for purchase');
```

### **Tagging Example**
Tags can classify or group cash instances:

```php
$cash->attachTags(['finance', 'budget']);
$cashWithTags = Cash::withAnyTags(['finance'])->get();
```

---

## Testing

### **Unit Testing**
- The suite uses **Pest** for fast and expressive unit tests.
- Coverage for:
    - Validation: `CashValidationRulesDataTest.php`, `CashInstructionDataTest.php`
    - Secure redemption: `CashSecretTest.php`
    - Tagging: `CashTagTest.php`
    - Statuses: `CashStatusTest.php`
    - Instructions and metadata: `VoucherInstructionsDataTest.php`

### **Sample Test:**
Tests ensure methods like `canRedeem` work as expected:

```php
it('does not allow redemption if cash has expired', function () {
    $cash = Cash::factory()->create([
        'expires_on' => now()->subDay(), // Expired yesterday
        'secret' => 'secure-secret',
    ]);

    $validSecret = 'secure-secret';

    expect($cash->canRedeem($validSecret))->toBeFalse(); // Fails due to expiration
});
```

Run the test suite:

```bash
./vendor/bin/pest
```

---

## Code Structure

### Models
- **`Cash`**: Core monetary entity with statuses, tags, and secure redemption.
- **`Voucher`**: A redeemable voucher that may have associated cash.

### Data Classes
- `CashValidationRulesData`: For cash-specific validation rules (e.g., mobile, location).
- `VoucherInstructionsData`: Holds all voucher-related instructions.

### Enums
- `CashStatus`: Tracks the current state of cash (e.g., `MINTED`, `EXPIRED`).
- `VoucherInputField`: Lists possible input fields for voucher instructions (e.g., `MOBILE`, `EMAIL`).

---

## Key Files

| File                          | Purpose                                             |
|-------------------------------|-----------------------------------------------------|
| `CashTest.php`                | Core tests for `Cash` model functionality           |
| `CashSecretTest.php`          | Tests secure secret handling and redemption logic   |
| `CashStatusTest.php`          | Tests status management for `Cash` entity           |
| `CashTagTest.php`             | Tests tagging functionality for `Cash` entity       |
| `VoucherInstructionsDataTest` | Tests voucher instructions and serialization        |

---

## Example Workflow

### 1. **Create a Voucher with Cash**:
Use structured data classes to create a voucher with cash, inputs, and validation:

```php
$instructions = new VoucherInstructionsData([
    'cash' => [
        'amount' => 1000,
        'currency' => 'PHP',
        'validation' => [
            'secret' => 'secure-secret',
        ],
    ],
]);

$voucher = Vouchers::create([
    'metadata' => ['instructions' => $instructions],
]);
```

### 2. **Redeem a Voucher**:
Validate cash redemption:

```php
$cash = $voucher->cash;

if ($cash->canRedeem($providedSecret)) {
    $cash->setStatus(CashStatus::DISBURSED);
}
```

---

## Conclusion

The **Digital Voucher System** with the `Cash` entity provides a robust framework for managing redeemable vouchers. Combined with secure secret handling, tagging, validation, and metadata storage, this system is designed to meet modern financial and transactional needs. Propel your application with this flexible and modular system! 🚀
