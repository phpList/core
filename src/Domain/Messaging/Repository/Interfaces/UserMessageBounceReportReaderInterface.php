<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository\Interfaces;

/**
 * Implemented by UserMessageBounceRepository (Doctrine/DB, plain SQL joins) and
 * UserMessageBounceElasticsearchHybridReader (Elasticsearch bounce counts merged with MySQL
 * Subscriber/Message data). Consumers are aliased to whichever one reads are configured to use -
 * see config/services/repositories.yml.
 *
 * Kept separate from UserMessageBounceReaderInterface: these two methods join the bounce data
 * against other entities for reporting, rather than answering from this entity's own data alone.
 */
interface UserMessageBounceReportReaderInterface
{
    /**
     * @return array<int, array{
     *   subscriber_id: int,
     *   email: string,
     *   confirmed: bool,
     *   blacklisted: bool,
     *   total_bounces: int
     * }>
     */
    public function getListBounceTotals(int $listId): array;

    /** @return array<int, array{message_id: int, subject: string, total_bounces: int}> */
    public function getCampaignBounceTotals(?int $ownerId = null): array;
}
