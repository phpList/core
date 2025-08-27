<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\SubscriberBlacklistService;

class BlacklistEmailAndDeleteBounceHandler implements BounceActionHandlerInterface
{
    private SubscriberHistoryManager $subscriberHistoryManager;
    private BounceManager $bounceManager;
    private SubscriberBlacklistService $blacklistService;

    public function __construct(
        SubscriberHistoryManager $subscriberHistoryManager,
        BounceManager $bounceManager,
        SubscriberBlacklistService $blacklistService
    ) {
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->bounceManager = $bounceManager;
        $this->blacklistService = $blacklistService;
    }

    public static function supports(string $action): bool
    {
        return $action === 'blacklistemailanddeletebounce';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber'])) {
            $this->blacklistService->blacklist(
                subscriber: $closureData['subscriber'],
                reason: 'Email address auto blacklisted by bounce rule '.$closureData['ruleId']
            );
            $this->subscriberHistoryManager->addHistory(
                $closureData['subscriber'],
                'Auto Unsubscribed',
                'User auto unsubscribed for bounce rule '.$closureData['ruleId']
            );
        }
        $this->bounceManager->delete($closureData['bounce']);
    }
}
