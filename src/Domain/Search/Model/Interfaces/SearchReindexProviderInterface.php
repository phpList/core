<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Search\Model\Interfaces;

/**
 * Batches an entity type out of the database for backfilling Elasticsearch. Implementations are
 * auto-tagged via config/services/elasticsearch.yml and picked up by the `phplist:search:reindex` command.
 */
interface SearchReindexProviderInterface
{
    public function getAlias(): string;

    public function countAll(): int;

    /** @return iterable<SearchIndexableInterface> */
    public function fetchBatch(int $lastId, int $batchSize): iterable;
}
