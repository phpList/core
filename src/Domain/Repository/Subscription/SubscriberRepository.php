<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Subscription;

use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Repository\AbstractRepository;

/**
 * Repository for Subscriber models.
 *
 * @method Subscriber findOneByEmail(string $email)
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberRepository extends AbstractRepository
{
    /**
     * Get subscribers by subscribed lists.
     *
     * @param int $listId The ID of the subscription list.
     * @return Subscriber[] Returns an array of Subscriber entities.
     */
    public function findSubscribersBySubscribedList(int $listId): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.subscribedLists', 'l')
            ->where('l.id = :listId')
            ->setParameter('listId', $listId)
            ->getQuery()
            ->getResult();
    }
}
