<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;

class UnconfirmUserHandler implements BounceActionHandlerInterface
{
    private SubscriberRepository $subscriberRepository;
    private SubscriberHistoryManager $subscriberHistoryManager;

    public function __construct(
        SubscriberRepository $subscriberRepository,
        SubscriberHistoryManager $subscriberHistoryManager,
    ) {
        $this->subscriberRepository = $subscriberRepository;
        $this->subscriberHistoryManager = $subscriberHistoryManager;
    }

    public static function supports(string $action): bool
    {
        return $action === 'unconfirmuser';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber']) && $closureData['confirmed']) {
            $this->subscriberRepository->markUnconfirmed($closureData['userId']);
            $this->subscriberHistoryManager->addHistory(
                $closureData['subscriber'],
                'Auto Unconfirmed',
                'Subscriber auto unconfirmed for bounce rule '.$closureData['ruleId']
            );
        }
    }
}
