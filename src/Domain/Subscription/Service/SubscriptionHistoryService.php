<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Model\Filter\SubscriptionHistoryFilter;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;

class SubscriptionHistoryService
{
    private SubscriberHistoryRepository $repository;

    public function __construct(SubscriberHistoryRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getHistory(int $lastId, int $limit, SubscriptionHistoryFilter $filter): array
    {
        return $this->repository->getFilteredAfterId($lastId, $limit, $filter);
    }
}
