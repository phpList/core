<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Search;

use DateInterval;
use DateTimeInterface;
use PhpList\Core\Domain\Search\Model\Interfaces\SearchPurgeProviderInterface;
use PhpList\Core\Domain\Subscription\Model\SubscriberHistory;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;

class SubscriberHistoryPurgeProvider implements SearchPurgeProviderInterface
{
    public function __construct(
        private readonly SubscriberHistoryRepository $repository,
        private readonly string $indexPrefix,
        private readonly string $retentionPeriod,
    ) {
    }

    public function getAlias(): string
    {
        return SubscriberHistory::SEARCH_INDEX_NAME;
    }

    public function getRetentionPeriod(): ?DateInterval
    {
        return $this->retentionPeriod !== '' ? new DateInterval($this->retentionPeriod) : null;
    }

    public function getSearchIndexName(): string
    {
        return $this->indexPrefix . SubscriberHistory::SEARCH_INDEX_NAME;
    }

    public function countOlderThan(DateTimeInterface $cutoff): int
    {
        return $this->repository->countOlderThan($cutoff);
    }

    public function fetchBatchOlderThan(DateTimeInterface $cutoff, int $lastId, int $batchSize): iterable
    {
        return $this->repository->fetchBatchOlderThan($cutoff, $lastId, $batchSize);
    }

    public function deleteByIds(array $ids): int
    {
        return $this->repository->deleteByIds($ids);
    }
}
