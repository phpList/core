<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Service\Handler;

interface BounceActionHandlerInterface
{
    public function supports(string $action): bool;
    public function handle(array $closureData): void;
}
