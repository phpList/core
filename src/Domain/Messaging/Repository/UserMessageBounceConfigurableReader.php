<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use DateTimeInterface;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Messaging\Model\Interfaces\UserMessageBounceRecordInterface;
use PhpList\Core\Domain\Messaging\Repository\Interfaces\UserMessageBounceReaderInterface;

/**
 * Read-side half of making Elasticsearch fully optional (see docs/ElasticsearchSearch.md and
 * SearchIndexDoctrineListener for the write-side half): delegates to the Elasticsearch reader when
 * elasticsearch.enabled is true, otherwise to the plain Doctrine repository. This is what
 * UserMessageBounceReaderInterface is aliased to in config/services/repositories.yml, so no consumer
 * needs to know which backend is active.
 */
class UserMessageBounceConfigurableReader implements UserMessageBounceReaderInterface
{
    public function __construct(
        private readonly UserMessageBounceRepository $databaseReader,
        private readonly UserMessageBounceElasticsearchReader $elasticsearchReader,
        private readonly bool $elasticsearchEnabled,
    ) {
    }

    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult
    {
        return $this->activeReader()->getFilteredAfterId($filter);
    }

    /** @return UserMessageBounceRecordInterface[] */
    public function getByUserId(int $userId): array
    {
        return $this->activeReader()->getByUserId($userId);
    }

    public function getCountByMessageId(int $messageId): int
    {
        return $this->activeReader()->getCountByMessageId($messageId);
    }

    public function countBetween(DateTimeInterface $start, DateTimeInterface $end): int
    {
        return $this->activeReader()->countBetween($start, $end);
    }

    public function existsByMessageIdAndUserId(int $messageId, int $subscriberId): bool
    {
        return $this->activeReader()->existsByMessageIdAndUserId($messageId, $subscriberId);
    }

    private function activeReader(): UserMessageBounceReaderInterface
    {
        return $this->elasticsearchEnabled ? $this->elasticsearchReader : $this->databaseReader;
    }
}
