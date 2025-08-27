<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberBlacklistService;

class BlacklistUserHandler implements BounceActionHandlerInterface
{
    private SubscriberHistoryManager $subscriberHistoryManager;
    private SubscriberBlacklistService $blacklistService;

    public function __construct(
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberBlacklistService $blacklistService,
    ) {
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->blacklistService = $blacklistService;
    }

    public static function supports(string $action): bool
    {
        return $action === 'blacklistuser';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber']) && !$closureData['blacklisted']) {
            $this->blacklistService->blacklist(
                subscriber: $closureData['subscriber'],
                reason: 'Subscriber auto blacklisted by bounce rule '.$closureData['ruleId']
            );
            $this->subscriberHistoryManager->addHistory(
                subscriber: $closureData['subscriber'],
                message: 'Auto Unsubscribed',
                details: 'User auto unsubscribed for bounce rule '.$closureData['ruleId']
            );
        }
    }
}
