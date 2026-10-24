<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Model\Interfaces;

use DateInterval;
use DateTimeInterface;

/**
 * Batches an entity type out of the database for deletion once rows are older than a configured
 * retention period and confirmed to exist in Elasticsearch. Implementations are auto-tagged via
 * config/services/elasticsearch.yml and picked up by the `phplist:search:purge` command.
 */
interface SearchPurgeProviderInterface
{
    public function getAlias(): string;

    /** Null means purge is disabled for this entity (no retention period configured). */
    public function getRetentionPeriod(): ?DateInterval;

    /** Physical Elasticsearch index name (prefix included) to verify rows against before deleting. */
    public function getSearchIndexName(): string;

    public function countOlderThan(DateTimeInterface $cutoff): int;

    /** @return iterable<SearchIndexableInterface> */
    public function fetchBatchOlderThan(DateTimeInterface $cutoff, int $lastId, int $batchSize): iterable;

    /** @param int[] $ids @return int number of rows deleted */
    public function deleteByIds(array $ids): int;
}
