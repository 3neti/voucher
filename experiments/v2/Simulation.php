<?php

declare(strict_types=1);

namespace VoucherV2Experiment;

use DomainException;

/** In-memory experiment: no persistence, provider calls, accounting or concurrency. */
final class CampaignCase
{
    public string $state = 'awaiting_payment';

    public int $issuances = 0;

    public int $charges = 0;

    public int $collectedMinor = 0;

    public array $receipts = [];

    public ?string $code = null;

    private array $fingerprints = [];

    public function __construct(public readonly string $campaign, public readonly int $productMinor, public readonly int $customerFeeMinor, public readonly int $issuerFeeMinor, public readonly string $currency = 'PHP') {}

    public function recognize(string $id, string $campaign, int $amountMinor, string $currency, bool $confirmed): void
    {
        $fingerprint = hash('sha256', json_encode([$campaign, $amountMinor, $currency, $confirmed], JSON_THROW_ON_ERROR));
        if (isset($this->fingerprints[$id])) {
            if ($this->fingerprints[$id] !== $fingerprint) {
                throw new DomainException('Conflicting replay.');
            }

            return;
        }
        if ($campaign !== $this->campaign || $currency !== $this->currency || ! $confirmed || $amountMinor <= 0 || $this->code !== null) {
            throw new DomainException('Payment is not eligible for this case.');
        }
        $this->fingerprints[$id] = $fingerprint;
        $this->receipts[$id] = ['amount_minor' => $amountMinor, 'currency' => $currency];
        $this->collectedMinor += $amountMinor;
        $due = $this->productMinor + $this->customerFeeMinor;
        $this->state = $this->collectedMinor < $due ? 'needs_resolution' : ($this->collectedMinor === $due ? 'payment_satisfied' : 'needs_resolution');
    }

    public function issue(int $availableIssuerMinor): void
    {
        if ($this->code !== null) {
            return;
        }
        if ($this->state !== 'payment_satisfied') {
            throw new DomainException('Payment requirement not satisfied.');
        }
        $this->code = 'AA317';
        $this->issuances++;
        $this->state = 'dormant';
        $this->activate($availableIssuerMinor);
    }

    public function activate(int $availableIssuerMinor): void
    {
        if ($this->state === 'active') {
            return;
        }
        if ($this->state !== 'dormant') {
            throw new DomainException('No dormant voucher.');
        }
        if ($availableIssuerMinor >= $this->issuerFeeMinor) {
            $this->charges += $this->issuerFeeMinor > 0 ? 1 : 0;
            $this->state = 'active';
        }
    }
}

final class SettlementCase
{
    public string $state = 'awaiting_attestation';

    public int $payouts = 0;

    private array $processed = [];

    public function __construct(private readonly bool $payerAuthenticationRequired = false) {}

    public function attest(string $id, string $actor = 'beneficiary'): void
    {
        if ($actor !== 'beneficiary') {
            throw new DomainException('Only the beneficiary can attest.');
        }
        if (isset($this->processed[$id])) {
            return;
        }
        if ($this->state !== 'awaiting_attestation') {
            throw new DomainException('Invalid attestation state.');
        }
        $this->processed[$id] = true;
        $this->state = 'awaiting_review';
    }

    public function approve(string $actor = 'hospital_reviewer', bool $authenticated = true): void
    {
        if ($actor !== 'hospital_reviewer' || ! $authenticated) {
            throw new DomainException('An authenticated hospital reviewer is required.');
        }
        if ($this->state !== 'awaiting_review') {
            throw new DomainException('Review requires attestation.');
        }
        $this->state = 'ready';
    }

    public function pay(string $id, bool $confirmed, string $actor = 'payer', bool $authenticated = false): void
    {
        if ($actor !== 'payer' || ($this->payerAuthenticationRequired && ! $authenticated)) {
            throw new DomainException('The payer is not eligible for this payment.');
        }
        if (isset($this->processed[$id])) {
            return;
        }
        if ($this->state !== 'ready' || ! $confirmed) {
            throw new DomainException('Settlement not ready or payment unconfirmed.');
        }
        $this->processed[$id] = true;
        $this->payouts++;
        $this->state = 'payout_pending';
    }

    public function payout(string $outcome): void
    {
        if ($this->state !== 'payout_pending' || ! in_array($outcome, ['confirmed', 'failed'], true)) {
            throw new DomainException('Invalid payout outcome.');
        }
        $this->state = $outcome === 'confirmed' ? 'settled' : 'recovery_required';
    }
}

final class LoanCase
{
    public string $state = 'awaiting_disbursement';

    public int $paidMinor = 0;

    private array $payments = [];

    public function __construct(public readonly int $targetMinor, public readonly int $installmentMinor) {}

    public function disburse(bool $finalized): void
    {
        if (! $finalized || $this->state !== 'awaiting_disbursement') {
            throw new DomainException('Disbursement must be finalized once.');
        }
        $this->state = 'repaying';
    }

    public function pay(string $id, int $amountMinor): void
    {
        if (isset($this->payments[$id])) {
            if ($this->payments[$id] !== $amountMinor) {
                throw new DomainException('Conflicting replay.');
            }

            return;
        }
        if ($this->state !== 'repaying' || $amountMinor !== $this->installmentMinor || $this->paidMinor + $amountMinor > $this->targetMinor) {
            throw new DomainException('Invalid repayment.');
        }
        $this->payments[$id] = $amountMinor;
        $this->paidMinor += $amountMinor;
        if ($this->paidMinor === $this->targetMinor) {
            $this->state = 'settled';
        }
    }
}
