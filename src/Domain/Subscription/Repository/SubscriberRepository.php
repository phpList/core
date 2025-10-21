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
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 */
class SubscriberRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    /**
     * @return Subscriber[]
     */
    public function findSubscribersWithoutUuid(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.uniqueId IS NULL OR s.uniqueId = :emptyString')
            ->setParameter('emptyString', '')
            ->getQuery()
            ->getResult();
    }

    public function findOneByEmail(string $email): ?Subscriber
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findOneByUniqueId(string $uniqueId): ?Subscriber
    {
        return $this->findOneBy(['uniqueId' => $uniqueId]);
    }

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

        $queryBuilder = $this->createQueryBuilder('subscriber')
            ->innerJoin('subscriber.subscriptions', 'subscription')
            ->innerJoin('subscription.subscriberList', 'list');

        if ($filter->getListId() !== null) {
            $queryBuilder->where('list.id = :listId')
                ->setParameter('listId', $filter->getListId());
            if ($filter->getSubscribedDateFrom() !== null) {
                $queryBuilder->where('subscription.createdAt > :subscribedAtFrom')
                    ->setParameter('subscribedAtFrom', $filter->getSubscribedDateFrom());
            }
            if ($filter->getSubscribedDateTo() !== null) {
                $queryBuilder->where('subscription.createdAt < :subscribedAtTo')
                    ->setParameter('subscribedAtTo', $filter->getSubscribedDateTo());
            }
        }
        if ($filter->getCreatedDateFrom() !== null) {
            $queryBuilder->where('subscriber.createdAt > :createdAtFrom')
                ->setParameter('createdAtFrom', $filter->getCreatedDateFrom());
        }
        if ($filter->getCreatedDateTo() !== null) {
            $queryBuilder->where('subscriber.createdAt < :createdAtTo')
                ->setParameter('createdAtTo', $filter->getCreatedDateTo());
        }
        if ($filter->getUpdatedDateFrom() !== null) {
            $queryBuilder->where('subscriber.updatedAt > :updatedAtFrom')
                ->setParameter('updatedAtFrom', $filter->getUpdatedDateFrom());
        }
        if ($filter->getUpdatedDateTo() !== null) {
            $queryBuilder->where('subscriber.updatedAt < :updatedAtTo')
                ->setParameter('updatedAtTo', $filter->getUpdatedDateTo());
        }

        return $queryBuilder->andWhere('subscriber.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('subscriber.id', 'ASC')
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

    public function isEmailBlacklisted(string $email): bool
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder();

        $queryBuilder->select('u.email')
            ->from(Subscriber::class, 'u')
            ->where('u.email = :email')
            ->andWhere('u.blacklisted = 1')
            ->setParameter('email', $email)
            ->setMaxResults(1);

        return !($queryBuilder->getQuery()->getOneOrNullResult() === null);
    }

    public function incrementBounceCount(int $subscriberId): void
    {
        $this->createQueryBuilder('s')
            ->update()
            ->set('s.bounceCount', 's.bounceCount + 1')
            ->where('s.id = :subscriberId')
            ->setParameter('subscriberId', $subscriberId)
            ->getQuery()
            ->execute();
    }

    public function markUnconfirmed(int $subscriberId): void
    {
        $this->createQueryBuilder('s')
            ->update()
            ->set('s.confirmed', ':confirmed')
            ->where('s.id = :id')
            ->setParameter('confirmed', false)
            ->setParameter('id', $subscriberId)
            ->getQuery()
            ->execute();
    }

    public function markConfirmed(int $subscriberId): void
    {
        $this->createQueryBuilder('s')
            ->update()
            ->set('s.confirmed', ':confirmed')
            ->where('s.id = :id')
            ->setParameter('confirmed', true)
            ->setParameter('id', $subscriberId)
            ->getQuery()
            ->execute();
    }

    /** @return Subscriber[] */
    public function distinctUsersWithBouncesConfirmedNotBlacklisted(): array
    {
        return $this->createQueryBuilder('s')
            ->select('s.id')
            ->where('s.bounceCount > 0')
            ->andWhere('s.confirmed = 1')
            ->andWhere('s.blacklisted = 0')
            ->getQuery()
            ->getScalarResult();
    }

    public function decrementBounceCount(Subscriber $subscriber): void
    {
        $this->createQueryBuilder('s')
            ->update()
            ->set('s.bounceCount', 's.bounceCount - 1')
            ->where('s.id = :subscriberId')
            ->setParameter('subscriberId', $subscriber->getId())
            ->getQuery()
            ->execute();
    }
}
