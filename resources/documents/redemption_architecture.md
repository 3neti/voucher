# Redemption Contract Validation Architecture

## Overview
The redemption validation system is modular and pluggable.

### Flow
1. ValidateRedemptionContract (Pipeline)
2. RedemptionContractEngine
3. Validators (Signature, Selfie, Location, etc.)
4. RedemptionEvidenceExtractor

## Components

### Pipeline (ValidateRedemptionContract)
- Delegates validation to engine
- Persists results
- Decides block vs continue

### Engine
- Aggregates validators
- Produces structured result:
  - passed
  - should_block
  - issues

### Validators
Each validator:
- Implements RedemptionRuleValidator
- Checks one concern
- Returns structured issues

### Evidence Extractor
- Normalizes redeemer metadata
- Provides clean input to validators

## Benefits
- Extensible
- Testable
- Maintainable
- Auditable
