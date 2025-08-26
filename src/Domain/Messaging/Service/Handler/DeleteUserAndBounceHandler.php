<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;

class DeleteUserAndBounceHandler implements BounceActionHandlerInterface
{
    public function __construct(
        private readonly BounceManager $bounceManager,
        private readonly SubscriberManager $subscriberManager,
    ) {}

    public function supports(string $action): bool
    {
        return $action === 'deleteuserandbounce';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber'])) {
            $this->subscriberManager->deleteSubscriber($closureData['subscriber']);
        }
        $this->bounceManager->delete($closureData['bounce']);
    }
}
