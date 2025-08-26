<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;

class DeleteBounceHandler implements BounceActionHandlerInterface
{
    public function __construct(
        private readonly BounceManager $bounceManager,
    ) {}

    public function supports(string $action): bool
    {
        return $action === 'deletebounce';
    }

    public function handle(array $closureData): void
    {
        $this->bounceManager->delete($closureData['bounce']);
    }
}
