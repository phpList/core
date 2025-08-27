<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

interface BounceActionHandlerInterface
{
    public static function supports(string $action): bool;
    public function handle(array $closureData): void;
}
