<?php

namespace LBHurtado\Voucher\Contracts;

use LBHurtado\Voucher\Data\ExecutionContextData;
use LBHurtado\Voucher\Data\ExecutionResultData;

interface ExecutionDriverContract
{
    public function key(): string;

    public function execute(ExecutionContextData $context): ExecutionResultData;
}
