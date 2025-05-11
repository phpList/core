<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

/**
 * Repository for Subscriber models.
 *
 * @method Subscriber|null findOneByEmail(string $email)
 *
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
class SubscriberRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    public function findSubscribersBySubscribedList(int $listId): ?Subscriber
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.subscriptions', 'subscription')
            ->innerJoin('subscription.subscriberList', 'list')
            ->where('list.id = :listId')
            ->setParameter('listId', $listId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return Subscriber[] */
    public function getSubscribersBySubscribedListId(int $listId): array
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.subscriptions', 'subscription')
            ->innerJoin('subscription.subscriberList', 'list')
            ->where('list.id = :listId')
            ->setParameter('listId', $listId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Subscriber[]
     * @throws InvalidArgumentException
     */
    public function getFilteredAfterId(int $lastId, int $limit, ?FilterRequestInterface $filter = null): array
    {
        if (!$filter instanceof SubscriberFilter) {
            throw new InvalidArgumentException('Expected SubscriberFilterRequest.');
        }

        $queryBuilder = $this->createQueryBuilder('s')
            ->innerJoin('s.subscriptions', 'subscription')
            ->innerJoin('subscription.subscriberList', 'list');

        if ($filter->getListId() !== null) {
            $queryBuilder->where('list.id = :listId')
                ->setParameter('listId', $filter->getListId());
        }

        return $queryBuilder->andWhere('s.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('s.id', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findSubscriberWithSubscriptions(int $id): ?Subscriber
    {
        return $this->createQueryBuilder('s')
            ->innerJoin('s.subscriptions', 'subscription')
            ->innerJoin('subscription.subscriberList', 'list')
            ->addSelect('subscription')
            ->addSelect('list')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
