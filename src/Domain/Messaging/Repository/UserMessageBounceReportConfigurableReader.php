<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use PhpList\Core\Domain\Messaging\Repository\Interfaces\UserMessageBounceReportReaderInterface;

/**
 * Read-side half of making Elasticsearch fully optional (see docs/ElasticsearchSearch.md and
 * SearchIndexDoctrineListener for the write-side half): delegates to the Elasticsearch-backed hybrid
 * reader when elasticsearch.enabled is true, otherwise to the plain Doctrine repository. This is what
 * UserMessageBounceReportReaderInterface is aliased to in config/services/repositories.yml, so no
 * consumer (e.g. phplist/rest-api's BounceController) needs to know which backend is active.
 */
class UserMessageBounceReportConfigurableReader implements UserMessageBounceReportReaderInterface
{
    public function __construct(
        private readonly UserMessageBounceRepository $databaseReader,
        private readonly UserMessageBounceElasticsearchHybridReader $elasticsearchReader,
        private readonly bool $elasticsearchEnabled,
    ) {
    }

    public function getListBounceTotals(int $listId): array
    {
        return $this->activeReader()->getListBounceTotals($listId);
    }

    public function getCampaignBounceTotals(?int $ownerId = null): array
    {
        return $this->activeReader()->getCampaignBounceTotals($ownerId);
    }

    private function activeReader(): UserMessageBounceReportReaderInterface
    {
        return $this->elasticsearchEnabled ? $this->elasticsearchReader : $this->databaseReader;
    }
}
