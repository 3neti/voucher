<?php

namespace LBHurtado\Voucher\Services;

use Closure;
use Illuminate\Contracts\Container\Container;
use LBHurtado\Voucher\Contracts\ExecutionPipelineStepContract;
use LBHurtado\Voucher\Exceptions\UnknownExecutionPipelineStepException;

class ExecutionPipelineStepRegistry
{
    /**
     * @var array<string, ExecutionPipelineStepContract|class-string<ExecutionPipelineStepContract>|Closure(Container): ExecutionPipelineStepContract>
     */
    private array $steps = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function register(string $key, ExecutionPipelineStepContract|string|Closure $step): self
    {
        $this->steps[$key] = $step;

        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->steps);
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->steps);
    }

    public function resolve(string $key): ExecutionPipelineStepContract
    {
        if (! $this->has($key)) {
            throw UnknownExecutionPipelineStepException::forKey($key);
        }

        $step = $this->steps[$key];

        if ($step instanceof ExecutionPipelineStepContract) {
            return $step;
        }

        if ($step instanceof Closure) {
            return $step($this->container);
        }

        return $this->container->make($step);
    }
}
