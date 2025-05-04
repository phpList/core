<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Subscription;

use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Subscription\SubscriberList;
use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Repository\Interfaces\PaginatableRepositoryInterface;

/**
 * Repository for SubscriberList models.
 *
 * @method SubscriberList[] findByOwner(Administrator $owner)
 *
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
class SubscriberListRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

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
