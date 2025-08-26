<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;

class UnconfirmUserHandler implements BounceActionHandlerInterface
{
    public function __construct(
        private readonly SubscriberManager $subscriberManager,
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
    ) {}

    public function supports(string $action): bool
    {
        return $action === 'unconfirmuser';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber']) && $closureData['confirmed']) {
            $this->subscriberManager->markUnconfirmed($closureData['userId']);
            $this->subscriberHistoryManager->addHistory(
                $closureData['subscriber'],
                'Auto Unconfirmed',
                'Subscriber auto unconfirmed for bounce rule '.$closureData['ruleId']
            );
        }
    }
}
