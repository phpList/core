<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;

class BlacklistUserAndDeleteBounceHandler implements BounceActionHandlerInterface
{
    private SubscriberHistoryManager $subscriberHistoryManager;
    private SubscriberManager $subscriberManager;
    private BounceManager $bounceManager;

    public function __construct(
        SubscriberHistoryManager $subscriberHistoryManager,
        SubscriberManager $subscriberManager,
        BounceManager $bounceManager,
    ) {
        $this->subscriberHistoryManager = $subscriberHistoryManager;
        $this->subscriberManager = $subscriberManager;
        $this->bounceManager = $bounceManager;
    }

    public static function supports(string $action): bool
    {
        return $action === 'blacklistuseranddeletebounce';
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
        $this->bounceManager->delete($closureData['bounce']);
    }
}
