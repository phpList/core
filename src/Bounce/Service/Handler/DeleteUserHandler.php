<?php

declare(strict_types=1);

namespace PhpList\Core\Bounce\Service\Handler;

use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use Psr\Log\LoggerInterface;

class DeleteUserHandler implements BounceActionHandlerInterface
{
    private SubscriberManager $subscriberManager;
    private LoggerInterface $logger;

    public function __construct(SubscriberManager $subscriberManager, LoggerInterface $logger)
    {
        $this->subscriberManager = $subscriberManager;
        $this->logger = $logger;
    }

    public function supports(string $action): bool
    {
        return $action === 'deleteuser';
    }

    public function handle(array $closureData): void
    {
        if (!empty($closureData['subscriber'])) {
            $this->logger->info('User deleted by bounce rule', [
                'user' => $closureData['subscriber']->getEmail(),
                'rule' => $closureData['ruleId'],
            ]);
            $this->subscriberManager->deleteSubscriber($closureData['subscriber']);
        }
    }
}
