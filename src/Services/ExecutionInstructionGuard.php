<?php

declare(strict_types=1);

namespace LBHurtado\Voucher\Services;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LogicException;

final class ExecutionInstructionGuard
{
    public function assertExecutable(ExecutionContextData $context): void
    {
        if ($context->instruction?->schema === 'voucher.execution.v2' && ! empty($context->instruction->events)) {
            throw new LogicException('V2 event execution is not implemented; refusing to ignore the instruction.');
        }
    }
}
