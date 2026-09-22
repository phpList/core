<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository\Interfaces;

use DateTimeInterface;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Messaging\Model\Interfaces\UserMessageBounceRecordInterface;

/**
 * Implemented by UserMessageBounceRepository (Doctrine/DB) and UserMessageBounceElasticsearchReader
 * (Elasticsearch). Consumers are aliased to whichever one reads are configured to use - see
 * config/services/repositories.yml.
 */
interface UserMessageBounceReaderInterface
{
    /** @return PaginatedResult<UserMessageBounceRecordInterface> */
    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult;

    /** @return UserMessageBounceRecordInterface[] */
    public function getByUserId(int $userId): array;

    public function getCountByMessageId(int $messageId): int;

    /**
     * @param int[] $messageIds
     * @return array<int,int> bounce counts keyed by message id
     */
    public function getCountByMessageIds(array $messageIds): array;

    public function countBetween(DateTimeInterface $start, DateTimeInterface $end): int;

    public function existsByMessageIdAndUserId(int $messageId, int $subscriberId): bool;
}
