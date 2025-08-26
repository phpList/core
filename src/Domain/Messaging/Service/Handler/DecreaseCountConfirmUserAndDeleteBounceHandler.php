<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;

class DecreaseCountConfirmUserAndDeleteBounceHandler implements BounceActionHandlerInterface
{
    public function __construct(
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
        private readonly SubscriberManager $subscriberManager,
        private readonly BounceManager $bounceManager,
    ) {}

    public function supports(string $action): bool
    {
        return $action === 'decreasecountconfirmuseranddeletebounce';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber'])) {
            $this->subscriberManager->decrementBounceCount($closureData['subscriber']);
            if (!$closureData['confirmed']) {
                $this->subscriberManager->markConfirmed($closureData['userId']);
                $this->subscriberHistoryManager->addHistory(
                    $closureData['subscriber'],
                    'Auto confirmed',
                    'Subscriber auto confirmed for bounce rule '.$closureData['ruleId']
                );
            }
        }
        $this->bounceManager->delete($closureData['bounce']);
    }
}
