<?php

namespace LBHurtado\Voucher\Tests\Fakes;

use LBHurtado\EmiCore\Contracts\PayoutProvider;
use LBHurtado\EmiCore\Data\PayoutRequestData;
use LBHurtado\EmiCore\Data\PayoutResultData;
use LBHurtado\EmiCore\Enums\SettlementRail;
use LBHurtado\EmiCore\Enums\PayoutStatus;
use Illuminate\Support\Str;
use RuntimeException;

class FakePayoutProvider implements PayoutProvider
{
    public ?PayoutRequestData $lastRequest = null;
    public int $disburseCallCount = 0;
    public int $checkStatusCallCount = 0;

    /**
     * @var array<int, PayoutRequestData>
     */
    public array $requests = [];

    public PayoutStatus $nextStatus = PayoutStatus::PENDING;
    public ?string $nextTransactionId = null;
    public ?string $nextUuid = null;
    public ?string $nextProvider = 'fake';
    public ?\Throwable $nextException = null;

    /**
     * Backward-compatible flag used by older tests.
     * When true, disburse() returns FAILED unless overridden by nextStatus.
     */
    public bool $shouldFail = false;

    public function reset(): self
    {
        $this->lastRequest = null;
        $this->disburseCallCount = 0;
        $this->checkStatusCallCount = 0;
        $this->requests = [];
        $this->nextStatus = PayoutStatus::PENDING;
        $this->nextTransactionId = null;
        $this->nextUuid = null;
        $this->nextProvider = 'fake';
        $this->nextException = null;
        $this->shouldFail = false;

        return $this;
    }

    public function willReturnSuccessfulResult(
        ?string $transactionId = null,
        ?string $uuid = null,
        ?string $provider = 'fake'
    ): self {
        $this->nextStatus = PayoutStatus::COMPLETED;
        $this->nextTransactionId = $transactionId;
        $this->nextUuid = $uuid;
        $this->nextProvider = $provider;
        $this->nextException = null;
        $this->shouldFail = false;

        return $this;
    }

    public function willReturnFailedResult(
        ?string $transactionId = null,
        ?string $uuid = null,
        ?string $provider = 'fake'
    ): self {
        $this->nextStatus = PayoutStatus::FAILED;
        $this->nextTransactionId = $transactionId;
        $this->nextUuid = $uuid;
        $this->nextProvider = $provider;
        $this->nextException = null;
        $this->shouldFail = true;

        return $this;
    }

    public function willReturnPendingResult(
        ?string $transactionId = null,
        ?string $uuid = null,
        ?string $provider = 'fake'
    ): self {
        $this->nextStatus = PayoutStatus::PENDING;
        $this->nextTransactionId = $transactionId;
        $this->nextUuid = $uuid;
        $this->nextProvider = $provider;
        $this->nextException = null;
        $this->shouldFail = false;

        return $this;
    }

    public function willThrow(?\Throwable $exception = null): self
    {
        $this->nextException = $exception ?? new RuntimeException('Fake payout provider timeout');

        return $this;
    }

    public function disburse(PayoutRequestData $request): PayoutResultData
    {
        if ($this->nextException) {
            throw $this->nextException;
        }

        $this->lastRequest = $request;
        $this->disburseCallCount++;
        $this->requests[] = $request;

        $status = $this->shouldFail ? PayoutStatus::FAILED : $this->nextStatus;
        $transactionId = $this->nextTransactionId
            ?? ($status === PayoutStatus::FAILED ? $request->reference : 'TXN-FAKE-'.Str::random(6));
        $uuid = $this->nextUuid ?? Str::uuid()->toString();
        $provider = $this->nextProvider ?? 'fake';

        return new PayoutResultData(
            transaction_id: $transactionId,
            uuid: $uuid,
            status: $status,
            provider: $provider,
        );
    }

    public function checkStatus(string $transactionId): PayoutResultData
    {
        $this->checkStatusCallCount++;


        return new PayoutResultData(
            transaction_id: $transactionId,
            uuid: $this->nextUuid ?? Str::uuid()->toString(),
            status: $this->nextStatus,
            provider: $this->nextProvider ?? 'fake',
        );
    }

    public function getRailFee(SettlementRail $rail): int
    {
        return match ($rail) {
            SettlementRail::INSTAPAY => 1000,
            SettlementRail::PESONET => 2500,
        };
    }

    public function assertDisburseCalledTimes(int $times): void
    {
        expect($this->disburseCallCount)->toBe($times);
    }

    public function assertNoDisbursementAttempted(): void
    {
        expect($this->disburseCallCount)->toBe(0);
    }

    public function assertLastRequest(callable $assertion): void
    {
        expect($this->lastRequest)->not->toBeNull();

        $assertion($this->lastRequest);
    }
}
