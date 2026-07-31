<?php

namespace LBHurtado\Voucher\Services;

use Closure;
use Illuminate\Contracts\Container\Container;
use LBHurtado\Voucher\Contracts\ExecutionDriverContract;
use LBHurtado\Voucher\Exceptions\UnknownExecutionDriverException;

class ExecutionDriverRegistry
{
    /**
     * @var array<string, ExecutionDriverContract|class-string<ExecutionDriverContract>|Closure(Container): ExecutionDriverContract>
     */
    private array $drivers = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function register(string $key, ExecutionDriverContract|string|Closure $driver): self
    {
        $this->drivers[$key] = $driver;

        return $this;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->drivers);
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys($this->drivers);
    }

    public function resolve(string $key): ExecutionDriverContract
    {
        if (! $this->has($key)) {
            throw UnknownExecutionDriverException::forKey($key);
        }

        $driver = $this->drivers[$key];

        if ($driver instanceof ExecutionDriverContract) {
            return $driver;
        }

        if ($driver instanceof Closure) {
            return $driver($this->container);
        }

        return $this->container->make($driver);
    }
}
