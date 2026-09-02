<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Search;

use PhpList\Core\Domain\Search\Model\Interfaces\SearchReindexProviderInterface;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;

class SubscriberHistoryReindexProvider implements SearchReindexProviderInterface
{
    public function __construct(private readonly SubscriberHistoryRepository $repository)
    {
    }

    public function getAlias(): string
    {
        return 'subscriber_history';
    }

    public function countAll(): int
    {
        return (int) $this->repository->createQueryBuilder('sh')
            ->select('COUNT(sh.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function fetchBatch(int $lastId, int $batchSize): iterable
    {
        return $this->repository->createQueryBuilder('sh')
            ->andWhere('sh.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('sh.id', 'ASC')
            ->setMaxResults($batchSize)
            ->getQuery()
            ->toIterable();
    }
}
