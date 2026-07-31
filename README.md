# Voucher

`3neti/voucher` defines the Pay Code instruction, issuance, claim, execution,
and redemption domain used by x-change.

## Capabilities

- Typed cash, input, validation, feedback, Rider, claim, and execution instructions
- Redeemable, payable, and settlement voucher policies
- Partial, divisible, and terminal redemption lifecycles
- Driver-composed execution through stored-value and settlement-envelope contracts
- Explicit onboarding and claim-outcome instructions
- Sanitized operational summaries and immutable instruction hydration

The package owns voucher-domain behavior. Provider calls, Cockpit screens,
commercial pricing, and host authentication remain outside this package.

## Installation

```bash
composer require 3neti/voucher
```

Laravel discovers `VoucherServiceProvider` automatically. Consuming packages
must publish or run their own installation workflow for migrations and host UI.

## Compatibility

- PHP 8.3 or newer
- Laravel 12 or 13
- EMI Core 2.0 beta or a compatible 2.x release
- Wallet 2.0 beta or a compatible 2.x release

## Verification

```bash
composer test
composer pint -- --test
composer validate --strict
composer audit
```

## Security Boundary

Voucher instructions describe authorized behavior; they do not prove provider
settlement, authorize arbitrary account credit, or substitute for execution
driver policy. Raw provider evidence and credentials must never be embedded in
voucher metadata or public summaries.

## License

Proprietary.
