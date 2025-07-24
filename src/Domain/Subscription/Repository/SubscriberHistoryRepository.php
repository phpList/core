<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use InvalidArgumentException;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriptionHistoryFilter;
use PhpList\Core\Domain\Subscription\Model\SubscriberHistory;

class SubscriberHistoryRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    /**
     * @return SubscriberHistory[]
     * @throws InvalidArgumentException
     */
    public function getFilteredAfterId(int $lastId, int $limit, ?FilterRequestInterface $filter = null): array
    {
        $queryBuilder = $this->createQueryBuilder('sh');

        if (!$filter instanceof SubscriptionHistoryFilter) {
            throw new InvalidArgumentException('Expected SubscriptionHistoryFilter.');
        }

        if ($filter->getSubscriber() !== null) {
            $queryBuilder->andWhere('sh.subscriber = :subscriber')
                ->setParameter('subscriber', $filter->getSubscriber());
        }

        if ($filter->getDateFrom() !== null) {
            $queryBuilder->andWhere('sh.date >= :date')
                ->setParameter('date', $filter->getDateFrom());
        }

        if ($filter->getIp() !== null) {
            $queryBuilder->andWhere('sh.ip = :ip')
                ->setParameter('ip', $filter->getIp());
        }

        if ($filter->getSummery() !== null) {
            $queryBuilder->andWhere('sh.summery = :summery')
                ->setParameter('summery', $filter->getSummery());
        }

        return $queryBuilder->getQuery()->getResult();
    }
}
