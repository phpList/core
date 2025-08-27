<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Service\Handler\BounceActionHandlerInterface;
use RuntimeException;

class BounceActionResolver
{
    /** @var BounceActionHandlerInterface[] */
    private array $handlers = [];

    /** @var array<string, BounceActionHandlerInterface> */
    private array $cache = [];

    /**
     * @param iterable<BounceActionHandlerInterface> $handlers
     */
    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            $this->handlers[] = $handler;
        }
    }

    public function has(string $action): bool
    {
        return isset($this->cache[$action]) || $this->find($action) !== null;
    }

    public function resolve(string $action): BounceActionHandlerInterface
    {
        if (isset($this->cache[$action])) {
            return $this->cache[$action];
        }

        $handler = $this->find($action);
        if ($handler === null) {
            throw new RuntimeException(sprintf('No handler found for action "%s".', $action));
        }

        $this->cache[$action] = $handler;

        return $handler;
    }

    /** Convenience: resolve + execute */
    public function handle(string $action, array $context): void
    {
        $this->resolve($action)->handle($context);
    }

    private function find(string $action): ?BounceActionHandlerInterface
    {
        foreach ($this->handlers as $handler) {
            if ($handler::supports($action)) {
                return $handler;
            }
        }

        return null;
    }
}
