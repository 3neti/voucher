# How to Add a New Redemption Validator

## Step 1: Create Validator
Implement RedemptionRuleValidator:

class OtpRuleValidator implements RedemptionRuleValidator

## Step 2: Implement Methods
- supports(Voucher $voucher)
- validate(Voucher $voucher, RedemptionEvidenceData $evidence)

Return an array of RedemptionValidationIssueData.

## Step 3: Register in Service Provider

$this->app->singleton(OtpRuleValidator::class);

$this->app->singleton(RedemptionContractEngine::class, function ($app) {
    return new RedemptionContractEngine(
        extractor: $app->make(RedemptionEvidenceExtractor::class),
        validators: [
            ...,
            $app->make(OtpRuleValidator::class),
        ],
    );
});

## Step 4: Extend DTO (optional)
Add new rule in VoucherInstructionsData if needed.

## Step 5: Write Tests
- validator unit test
- integration via pipeline

## Done
Your validator is now automatically enforced.
