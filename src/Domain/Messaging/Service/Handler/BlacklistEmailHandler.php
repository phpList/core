<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberBlacklistService;

class BlacklistEmailHandler implements BounceActionHandlerInterface
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

    public function supports(string $action): bool
    {
        return $action === 'blacklistemail';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber'])) {
            $this->blacklistService->blacklist(
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
