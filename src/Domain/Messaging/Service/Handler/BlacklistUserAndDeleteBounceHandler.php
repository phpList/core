<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;

class BlacklistUserAndDeleteBounceHandler implements BounceActionHandlerInterface
{
    public function __construct(
        private readonly SubscriberHistoryManager $subscriberHistoryManager,
        private readonly SubscriberManager $subscriberManager,
        private readonly BounceManager $bounceManager,
    ) {}

    public function supports(string $action): bool
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
