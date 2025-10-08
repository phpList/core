<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Common\ClientIpResolver;
use PhpList\Core\Domain\Common\SystemInfoCollector;
use PhpList\Core\Domain\Identity\Model\Administrator;
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

    public function addHistoryFromImport(
        Subscriber $subscriber,
        array $listLines,
        array $updatedData,
        ?Administrator $admin = null,
    ): void {
        if (!$admin) {
            $headerLine = 'API-v2-import - CLI: ' . PHP_EOL . PHP_EOL;
        } else {
            $headerLine = 'API-v2-import - adminId: ' . $admin->getId() . PHP_EOL . PHP_EOL;
        }

        $lines = [];

        if (empty($updatedData) && empty($listLines)) {
            $lines[] = 'No user details changed';
        } else {
            $skip = ['password', 'modified'];
            foreach ($updatedData as $field => [$old, $new]) {
                if (in_array($field, $skip, true)) {
                    continue;
                }
                $lines[] = sprintf(
                    "%s = %s\n*changed* from %s",
                    $field,
                    json_encode($new),
                    json_encode($old)
                );
            }
            foreach ($listLines as $line) {
                $lines[] = $line;
            }
        }

        $this->addHistory(
            subscriber: $subscriber,
            message: 'Import by ' . $admin->getLoginName(),
            details: $headerLine . implode(PHP_EOL, $lines) . PHP_EOL
        );
    }
}
