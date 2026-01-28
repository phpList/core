<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\UserMessageForward;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

class UserMessageForwardManager
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function create(
        Subscriber $subscriber,
        Message $campaign,
        string $friendEmail,
        string $status
    ): UserMessageForward {
        $forward = (new UserMessageForward())
            ->setMessageId($campaign->getId())
            ->setUserId($subscriber->getId())
            ->setForward($friendEmail)
            ->setStatus($status);

        $this->entityManager->persist($forward);
        return $forward;
    }
}
