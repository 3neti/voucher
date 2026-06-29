<?php

namespace LBHurtado\Voucher\Services;

use Closure;
use LBHurtado\Voucher\Data\ExecutionPipelineStateData;

class ExecutionPipelineRuntime
{
    public function __construct(
        private readonly ExecutionPipelineStepRegistry $steps,
    ) {}

    /**
     * @param  array<int, string>  $pipeline
     */
    public function run(ExecutionPipelineStateData $state, array $pipeline): ExecutionPipelineStateData
    {
        $carry = array_reduce(
            array_reverse($pipeline),
            fn (Closure $next, string $stepKey): Closure => fn (ExecutionPipelineStateData $state): ExecutionPipelineStateData => $this->executeStep($stepKey, $state, $next),
            fn (ExecutionPipelineStateData $state): ExecutionPipelineStateData => $state,
        );

        return $carry($state);
    }

    private function executeStep(string $stepKey, ExecutionPipelineStateData $state, Closure $next): ExecutionPipelineStateData
    {
        if ($state->result !== null) {
            return $state;
        }

        return $this->steps
            ->resolve($stepKey)
            ->handle($state, function (ExecutionPipelineStateData $state) use ($next): ExecutionPipelineStateData {
                if ($state->result !== null) {
                    return $state;
                }

                return $next($state);
            });
    }
}
