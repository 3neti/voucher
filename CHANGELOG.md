# Changelog

All notable changes to `3neti/voucher` are documented here.

## v1.0.0-beta.1 - 2026-07-31

### Added
- Driver-composed execution engine and typed execution instructions
- Claim outcomes, claimant policies, onboarding intent, and completion metadata
- Settlement-envelope and stored-value execution drivers
- Structured Rider Stamp composition contract
- Sanitized operational voucher summaries

### Changed
- Require immutable Cash, Contact, EMI Core, model-input, voucher, settlement,
  and Wallet package releases instead of local paths or mutable branches
- Support Laravel 12/13 and Pest 3/4 through the package CI matrix

### Security
- Harden claim policy invariants and preserve scalar voucher types through
  instruction validation and hydration
