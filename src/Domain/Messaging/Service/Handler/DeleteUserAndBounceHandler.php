<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;

class DeleteUserAndBounceHandler implements BounceActionHandlerInterface
{
    private BounceManager $bounceManager;
    private SubscriberManager $subscriberManager;

    public function __construct(BounceManager $bounceManager, SubscriberManager $subscriberManager,)
    {
        $this->bounceManager = $bounceManager;
        $this->subscriberManager = $subscriberManager;
    }

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
