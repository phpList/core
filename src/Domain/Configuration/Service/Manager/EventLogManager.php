<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Service\Manager;

use DateTimeImmutable;
use DateTimeInterface;
use PhpList\Core\Domain\Configuration\Model\EventLog;
use PhpList\Core\Domain\Configuration\Model\Filter\EventLogFilter;
use PhpList\Core\Domain\Configuration\Repository\EventLogRepository;

class EventLogManager
{
    private EventLogRepository $repository;

    public function __construct(EventLogRepository $repository)
    {
        $this->repository = $repository;
    }

    public function log(string $page, string $entry): EventLog
    {
        $log = (new EventLog())
            ->setEntered(new DateTimeImmutable())
            ->setPage($page)
            ->setEntry($entry);

        $this->repository->save($log);

        return $log;
    }

    /**
     * Get event logs with optional filters (page and date range) and cursor pagination.
     *
     * @return EventLog[]
     */
    public function get(
        int $lastId = 0,
        int $limit = 50,
        ?string $page = null,
        ?DateTimeInterface $dateFrom = null,
        ?DateTimeInterface $dateTo = null
    ): array {
        $filter = new EventLogFilter($page, $dateFrom, $dateTo);
        return $this->repository->getFilteredAfterId($lastId, $limit, $filter);
    }

    public function delete(EventLog $log): void
    {
        $this->repository->remove($log);
    }
}
