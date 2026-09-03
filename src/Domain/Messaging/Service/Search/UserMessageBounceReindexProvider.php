<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Service\Search;

use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchReindexProviderInterface;

class UserMessageBounceReindexProvider implements SearchReindexProviderInterface
{
    public function __construct(private readonly UserMessageBounceRepository $repository)
    {
    }

    public function getAlias(): string
    {
        return UserMessageBounce::SEARCH_INDEX_NAME;
    }

    public function countAll(): int
    {
        return (int) $this->repository->createQueryBuilder('umb')
            ->select('COUNT(umb.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function fetchBatch(int $lastId, int $batchSize): iterable
    {
        return $this->repository->createQueryBuilder('umb')
            ->andWhere('umb.id > :lastId')
            ->setParameter('lastId', $lastId)
            ->orderBy('umb.id', 'ASC')
            ->setMaxResults($batchSize)
            ->getQuery()
            ->toIterable();
    }
}
