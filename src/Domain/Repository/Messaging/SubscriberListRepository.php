<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Messaging;

use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Messaging\SubscriberList;
use PhpList\Core\Domain\Repository\AbstractRepository;

/**
 * Repository for SubscriberList models.
 *
 * @method SubscriberList[] findByOwner(Administrator $owner)
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberListRepository extends AbstractRepository
{
    public function findWithSubscription($id)
    {
        return $this->createQueryBuilder('sl')
            ->innerJoin('sl.subscriptions', 's')
            ->addSelect('s')
            ->where('sl.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
