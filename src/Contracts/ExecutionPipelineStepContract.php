<?php

namespace LBHurtado\Voucher\Contracts;

use Closure;
use LBHurtado\Voucher\Data\ExecutionPipelineStateData;

interface ExecutionPipelineStepContract
{
    public function handle(ExecutionPipelineStateData $state, Closure $next): ExecutionPipelineStateData;
}
