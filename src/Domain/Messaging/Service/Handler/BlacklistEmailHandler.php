<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;

class BlacklistEmailHandler implements BounceActionHandlerInterface
{
    private SubscriberHistoryManager $subscriberHistoryManager;
    private SubscriberManager $subscriberManager;

    public function __construct(
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberManager $subscriberManager,
    ) {
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->subscriberManager = $subscriberManager;
    }

    public function supports(string $action): bool
    {
        return $action === 'blacklistemail';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber'])) {
            $this->subscriberManager->blacklist(
                $closureData['subscriber'],
                'Email address auto blacklisted by bounce rule '.$closureData['ruleId']
            );
            $this->subscriberHistoryManager->addHistory(
                $closureData['subscriber'],
                'Auto Unsubscribed',
                'email auto unsubscribed for bounce rule '.$closureData['ruleId']
            );
        }
    }
}
