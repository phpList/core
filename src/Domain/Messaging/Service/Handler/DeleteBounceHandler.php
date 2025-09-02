<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;

class DeleteBounceHandler implements BounceActionHandlerInterface
{
    private BounceManager $bounceManager;

    public function __construct(BounceManager $bounceManager)
    {
        $this->bounceManager = $bounceManager;
    }

    public function supports(string $action): bool
    {
        return $action === 'deletebounce';
    }

    public function handle(array $closureData): void
    {
        $this->bounceManager->delete($closureData['bounce']);
    }
}
