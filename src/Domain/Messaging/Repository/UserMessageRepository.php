<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use DateTimeInterface;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

class UserMessageRepository extends AbstractRepository
{
    public function findOneByUserAndMessage(Subscriber $subscriber, Message $campaign): ?UserMessage
    {
        return $this->findOneBy(['user' => $subscriber, 'message' => $campaign]);
    }

    /**
     * Counts how many user messages have status "sent" since the given time.
     */
    public function countSentSince(DateTimeInterface $since): int
    {
        $queryBuilder = $this->createQueryBuilder('um');
        $queryBuilder->select('COUNT(um)')
            ->where('um.createdAt > :since')
            ->andWhere('um.status = :status')
            ->setParameter('since', $since)
            ->setParameter('status', UserMessageStatus::Sent->value);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }
}
