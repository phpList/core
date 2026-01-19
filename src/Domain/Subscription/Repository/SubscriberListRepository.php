<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;

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

    /** @return SubscriberList[] */
    public function getListsByMessage(Message $message): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.listMessages', 'lm')
            ->join('lm.message', 'm')
            ->where('m = :message')
            ->setParameter('message', $message)
            ->getQuery()
            ->getResult();
    }

    public function getAllActive(): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.active = true')
            ->getQuery()
            ->getResult();
    }

    public function getListNames(array $listIds): array
    {
        if ($listIds === []) {
            return [];
        }

        $lists = $this->createQueryBuilder('l')
            ->select('l.name')
            ->where('l.id IN (:ids)')
            ->setParameter('ids', $listIds)
            ->getQuery()
            ->getScalarResult();

        return array_column($lists, 'name');
    }

    /**
     * Returns the names of lists the given subscriber is subscribed to.
     * If $showPrivate is false, only active/public lists are included.
     */
    public function getActiveListNamesForSubscriber(Subscriber $subscriber, bool $showPrivate): array
    {
        $queryBuilder = $this->createQueryBuilder('l')
            ->select('l.name')
            ->innerJoin('l.subscriptions', 's')
            ->where('IDENTITY(s.subscriber) = :subscriberId')
            ->setParameter('subscriberId', $subscriber->getId());

        if (!$showPrivate) {
            $queryBuilder->andWhere('l.active = true');
        }

        $rows = $queryBuilder->getQuery()->getScalarResult();

        return array_column($rows, 'name');
    }
}
