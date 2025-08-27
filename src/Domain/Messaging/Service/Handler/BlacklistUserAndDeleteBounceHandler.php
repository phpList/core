<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberBlacklistService;

class BlacklistUserAndDeleteBounceHandler implements BounceActionHandlerInterface
{
    private SubscriberHistoryManager $subscriberHistoryManager;
    private BounceManager $bounceManager;
    private SubscriberBlacklistService $blacklistService;

    public function __construct(
        SubscriberHistoryManager $subscriberHistoryManager,
        BounceManager $bounceManager,
        SubscriberBlacklistService $blacklistService,
    ) {
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->bounceManager = $bounceManager;
        $this->blacklistService = $blacklistService;
    }

    public static function supports(string $action): bool
    {
        return $action === 'blacklistuseranddeletebounce';
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
        $this->bounceManager->delete($closureData['bounce']);
    }
}
