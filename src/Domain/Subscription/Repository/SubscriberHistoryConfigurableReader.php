<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Subscription\Model\Interfaces\SubscriberHistoryRecordInterface;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\Interfaces\SubscriberHistoryReaderInterface;

/**
 * Read-side half of making Elasticsearch fully optional (see docs/ElasticsearchSearch.md and
 * SearchIndexDoctrineListener for the write-side half): delegates to the Elasticsearch reader when
 * elasticsearch.enabled is true, otherwise to the plain Doctrine repository. This is what
 * SubscriberHistoryReaderInterface is aliased to in config/services/repositories.yml, so no consumer
 * needs to know which backend is active.
 */
class SubscriberHistoryConfigurableReader implements SubscriberHistoryReaderInterface
{
    public function __construct(
        private readonly SubscriberHistoryRepository $databaseReader,
        private readonly SubscriberHistoryElasticsearchReader $elasticsearchReader,
        private readonly bool $elasticsearchEnabled,
    ) {
    }

    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult
    {
        return $this->activeReader()->getFilteredAfterId($filter);
    }

    /** @return SubscriberHistoryRecordInterface[] */
    public function getBySubscriber(Subscriber $subscriber): array
    {
        return $this->activeReader()->getBySubscriber($subscriber);
    }

    private function activeReader(): SubscriberHistoryReaderInterface
    {
        return $this->elasticsearchEnabled ? $this->elasticsearchReader : $this->databaseReader;
    }
}
