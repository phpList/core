<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use DateTimeInterface;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

/**
 * Repository for Subscriber models.
 *
 * @author Oliver Klee <oliver@phplist.com>
 * @author Tatevik Grigoryan <tatevik@phplist.com>
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
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

    public function findOneByForeignKey(string $foreignKey): ?Subscriber
    {
        return $this->findOneBy(['foreignKey' => $foreignKey]);
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
     * @return PaginatedResult<Subscriber>
     * @throws InvalidArgumentException
     * @SuppressWarnings("CyclomaticComplexity")
     * @SuppressWarnings("NPathComplexity")
     */
    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult
    {
        if (!$filter instanceof SubscriberFilter) {
            throw new InvalidArgumentException('Expected SubscriberFilter.');
        }

        $lastId = $filter->getLastId();
        $limit = $filter->getLimit();

        $applyFilters = function (QueryBuilder $queryBuilder) use ($filter): void {
            $queryBuilder
                ->leftJoin('subscriber.subscriptions', 'subscription')
                ->leftJoin('subscription.subscriberList', 'list');

            $this->applyListIdFilter($filter, $queryBuilder);
            $this->applyTimeFilter($filter, $queryBuilder);

            if ($filter->getIsConfirmed() !== null) {
                $queryBuilder
                    ->andWhere('subscriber.confirmed = :isConfirmed')
                    ->setParameter('isConfirmed', $filter->getIsConfirmed());
            }

            if ($filter->getIsBlacklisted() !== null) {
                $queryBuilder
                    ->andWhere('subscriber.blacklisted = :isBlacklisted')
                    ->setParameter('isBlacklisted', $filter->getIsBlacklisted());
            }

            if ($filter->getFindColumn() && $filter->getFindValue()) {
                $queryBuilder
                    ->andWhere(sprintf('subscriber.%s LIKE :search', $filter->getFindColumn()))
                    ->setParameter('search', '%' . $filter->getFindValue() . '%');
            }
        };

        $countQb = $this->createQueryBuilder('subscriber')
            ->select('COUNT(DISTINCT subscriber.id)');

        $applyFilters($countQb);

        $total = (int) $countQb
            ->getQuery()
            ->getSingleScalarResult();

        $idsQb = $this->createQueryBuilder('subscriber')
            ->select('DISTINCT subscriber.id');

        $applyFilters($idsQb);

        $rawIds = $idsQb
            ->andWhere('subscriber.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('subscriber.id', 'ASC')
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getScalarResult();

        $ids = array_map(static fn(array $row): int => (int) $row['id'], $rawIds);

        $hasMore = count($ids) > $limit;
        if ($hasMore) {
            array_pop($ids);
        }

        if ($ids === []) {
            return new PaginatedResult(
                items: [],
                total: $total,
                limit: $limit,
                lastId: $lastId,
            );
        }

        /** @var list<Subscriber> $items */
        $items = $this->createQueryBuilder('subscriber')
            ->select('DISTINCT subscriber, subscription, list')
            ->leftJoin('subscriber.subscriptions', 'subscription')
            ->leftJoin('subscription.subscriberList', 'list')
            ->andWhere('subscriber.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('subscriber.id', 'ASC')
            ->getQuery()
            ->getResult();

        usort($items, static fn(Subscriber $first, Subscriber $second): int => $first->getId() <=> $second->getId());

        return new PaginatedResult(
            items: $items,
            total: $total,
            limit: $limit,
            lastId: $lastId,
        );
    }

    private function applyListIdFilter(SubscriberFilter $filter, QueryBuilder $queryBuilder): void
    {
        if ($filter->getListId() !== null) {
            $queryBuilder
                ->andWhere('list.id = :listId')
                ->setParameter('listId', $filter->getListId());

            if ($filter->getSubscribedDateFrom() !== null) {
                $queryBuilder
                    ->andWhere('subscription.createdAt > :subscribedAtFrom')
                    ->setParameter('subscribedAtFrom', $filter->getSubscribedDateFrom());
            }

            if ($filter->getSubscribedDateTo() !== null) {
                $queryBuilder
                    ->andWhere('subscription.createdAt < :subscribedAtTo')
                    ->setParameter('subscribedAtTo', $filter->getSubscribedDateTo());
            }
        }
    }

    private function applyTimeFilter(SubscriberFilter $filter, QueryBuilder $queryBuilder): void
    {
        if ($filter->getCreatedDateFrom() !== null) {
            $queryBuilder->andWhere('subscriber.createdAt > :createdAtFrom')
                ->setParameter('createdAtFrom', $filter->getCreatedDateFrom());
        }
        if ($filter->getCreatedDateTo() !== null) {
            $queryBuilder->andWhere('subscriber.createdAt < :createdAtTo')
                ->setParameter('createdAtTo', $filter->getCreatedDateTo());
        }
        if ($filter->getUpdatedDateFrom() !== null) {
            $queryBuilder->andWhere('subscriber.updatedAt > :updatedAtFrom')
                ->setParameter('updatedAtFrom', $filter->getUpdatedDateFrom());
        }
        if ($filter->getUpdatedDateTo() !== null) {
            $queryBuilder->andWhere('subscriber.updatedAt < :updatedAtTo')
                ->setParameter('updatedAtTo', $filter->getUpdatedDateTo());
        }
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

    public function getDataById(int $subscriberId): array
    {
        return $this->createQueryBuilder('s')
            ->select('s')
            ->where('s.id = :subscriberId')
            ->setParameter('subscriberId', $subscriberId)
            ->getQuery()
            ->getArrayResult()[0] ?? [];
    }

    /**
     * Counts subscribers created between two dates.
     *
     * @param DateTimeInterface $start
     * @param DateTimeInterface $end
     * @return int
     */
    public function countCreatedBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.createdAt >= :start')
            ->andWhere('s.createdAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return Subscriber[] */
    public function getByEmails(array $emails): array
    {
        if (empty($emails)) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->select('s')
            ->where('s.email IN (:emails)')
            ->setParameter('emails', $emails)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the top domains (by subscriber count) among subscribers with a valid email address.
     * Aggregation happens in SQL so only $limit rows are ever loaded into memory.
     *
     * @return array<int, array{domain: string, subscribers: int|string}>
     */
    public function getTopDomains(int $limit, int $minSubscribers): array
    {
        return $this->createQueryBuilder('s')
            ->select("SUBSTRING(s.email, LOCATE('@', s.email) + 1, LENGTH(s.email)) AS domain")
            ->addSelect('COUNT(s.id) AS subscribers')
            ->where("LOCATE('@', s.email) > 0")
            ->groupBy('domain')
            ->having('COUNT(s.id) >= :minSubscribers')
            ->setParameter('minSubscribers', $minSubscribers)
            ->orderBy('subscribers', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Returns per-domain confirmed/unconfirmed/blacklisted subscriber counts, ordered by unconfirmed count.
     * Aggregation happens in SQL so only $limit rows are ever loaded into memory.
     *
     * @return array<int, array{domain: string, total: int|string, confirmed: int|string,
     *     unconfirmed: int|string, blacklisted: int|string}>
     */
    public function getDomainConfirmationStatistics(int $limit): array
    {
        return $this->createQueryBuilder('s')
            ->select("SUBSTRING(s.email, LOCATE('@', s.email) + 1, LENGTH(s.email)) AS domain")
            ->addSelect('COUNT(s.id) AS total')
            ->addSelect('SUM(CASE WHEN s.blacklisted = true THEN 1 ELSE 0 END) AS blacklisted')
            ->addSelect('SUM(CASE WHEN s.blacklisted = false AND s.confirmed = true THEN 1 ELSE 0 END) AS confirmed')
            ->addSelect(
                'SUM(CASE WHEN s.blacklisted = false AND s.confirmed = false THEN 1 ELSE 0 END) AS unconfirmed'
            )
            ->where("LOCATE('@', s.email) > 0")
            ->groupBy('domain')
            ->orderBy('unconfirmed', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Returns the top local-parts (by subscriber count) among subscribers with a valid email address.
     * Aggregation happens in SQL so only $limit rows are ever loaded into memory.
     *
     * @return array<int, array{localPart: string, count: int|string}>
     */
    public function getTopLocalParts(int $limit): array
    {
        return $this->createQueryBuilder('s')
            ->select("SUBSTRING(s.email, 1, LOCATE('@', s.email) - 1) AS localPart")
            ->addSelect('COUNT(s.id) AS count')
            ->where("LOCATE('@', s.email) > 0")
            ->groupBy('localPart')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Counts subscribers whose email address contains an '@'.
     */
    public function countWithValidEmail(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where("LOCATE('@', s.email) > 0")
            ->getQuery()
            ->getSingleScalarResult();
    }
}
