<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Handler;

use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberManager;
use Psr\Log\LoggerInterface;

class DeleteUserHandler implements BounceActionHandlerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly SubscriberManager $subscriberManager,
    ) {}

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
