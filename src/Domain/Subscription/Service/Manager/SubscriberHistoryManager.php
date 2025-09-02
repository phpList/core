<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Common\ClientIpResolver;
use PhpList\Core\Domain\Common\SystemInfoCollector;
use PhpList\Core\Domain\Subscription\Model\Filter\SubscriberHistoryFilter;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberHistory;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;

class SubscriberHistoryManager
{
    private SubscriberHistoryRepository $repository;
    private ClientIpResolver $clientIpResolver;
    private SystemInfoCollector $systemInfoCollector;

    public function __construct(
        SubscriberHistoryRepository $repository,
        ClientIpResolver $clientIpResolver,
        SystemInfoCollector $systemInfoCollector,
    ) {
        $this->repository = $repository;
        $this->clientIpResolver = $clientIpResolver;
        $this->systemInfoCollector = $systemInfoCollector;
    }

    public function getHistory(int $lastId, int $limit, SubscriberHistoryFilter $filter): array
    {
        return $this->repository->getFilteredAfterId($lastId, $limit, $filter);
    }

    public function addHistory(Subscriber $subscriber, string $message, ?string $details = null): SubscriberHistory
    {
        $subscriberHistory = new SubscriberHistory($subscriber);
        $subscriberHistory->setSummary($message);
        $subscriberHistory->setDetail($details ?? $message);
        $subscriberHistory->setSystemInfo($this->systemInfoCollector->collectAsString());
        $subscriberHistory->setIp($this->clientIpResolver->resolve());

        $this->repository->save($subscriberHistory);

        return $subscriberHistory;
    }
}
