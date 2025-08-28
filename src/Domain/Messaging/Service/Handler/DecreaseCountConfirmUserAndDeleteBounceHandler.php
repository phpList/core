<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;

class DecreaseCountConfirmUserAndDeleteBounceHandler implements BounceActionHandlerInterface
{
    private SubscriberHistoryManager $subscriberHistoryManager;
    private SubscriberManager $subscriberManager;
    private BounceManager $bounceManager;
    private SubscriberRepository $subscriberRepository;

    public function __construct(
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberManager $subscriberManager,
        BounceManager $bounceManager,
        SubscriberRepository $subscriberRepository,
    ) {
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->subscriberManager = $subscriberManager;
        $this->bounceManager = $bounceManager;
        $this->subscriberRepository = $subscriberRepository;
    }

    public function supports(string $action): bool
    {
        return $action === 'decreasecountconfirmuseranddeletebounce';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber'])) {
            $this->subscriberManager->decrementBounceCount($closureData['subscriber']);
            if (!$closureData['confirmed']) {
                $this->subscriberRepository->markConfirmed($closureData['userId']);
                $this->subscriberHistoryManager->addHistory(
                    subscriber: $closureData['subscriber'],
                    message: 'Auto confirmed',
                    details: 'Subscriber auto confirmed for bounce rule '.$closureData['ruleId']
                );
            }
        }
        $this->bounceManager->delete($closureData['bounce']);
    }
}
