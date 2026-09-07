<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Repository\Interfaces;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Subscription\Model\Interfaces\SubscriberHistoryRecordInterface;
use PhpList\Core\Domain\Subscription\Model\Subscriber;

/**
 * Implemented by SubscriberHistoryRepository (Doctrine/DB) and SubscriberHistoryElasticsearchReader
 * (Elasticsearch). SubscriberHistoryManager and SubscriberManager are aliased to whichever one reads
 * are configured to use - see config/services/repositories.yml.
 */
interface SubscriberHistoryReaderInterface
{
    /** @return PaginatedResult<SubscriberHistoryRecordInterface> */
    public function getFilteredAfterId(FilterRequestInterface $filter): PaginatedResult;

    /** @return SubscriberHistoryRecordInterface[] */
    public function getBySubscriber(Subscriber $subscriber): array;
}
