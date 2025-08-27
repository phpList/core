<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;

class BlacklistUserHandler implements BounceActionHandlerInterface
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

    public static function supports(string $action): bool
    {
        return $action === 'blacklistuser';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber']) && !$closureData['blacklisted']) {
            $this->subscriberManager->blacklist(
                $closureData['subscriber'],
                'Subscriber auto blacklisted by bounce rule '.$closureData['ruleId']
            );
            $this->subscriberHistoryManager->addHistory(
                $closureData['subscriber'],
                'Auto Unsubscribed',
                'User auto unsubscribed for bounce rule '.$closureData['ruleId']
            );
        }
    }
}
