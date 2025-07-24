<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberHistoryFilter;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;

class SubscriberHistoryManager
{
    private SubscriberHistoryRepository $repository;

    public function __construct(SubscriberHistoryRepository $repository)
    {
        $this->repository = $repository;
    }

    public function getHistory(int $lastId, int $limit, SubscriberHistoryFilter $filter): array
    {
        return $this->repository->getFilteredAfterId($lastId, $limit, $filter);
    }
}
