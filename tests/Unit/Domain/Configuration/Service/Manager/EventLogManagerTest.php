<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Configuration\Service\Manager;

use DateTimeImmutable;
use PhpList\Core\Domain\Configuration\Model\EventLog;
use PhpList\Core\Domain\Configuration\Model\Filter\EventLogFilter;
use PhpList\Core\Domain\Configuration\Repository\EventLogRepository;
use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class EventLogManagerTest extends TestCase
{
    private EventLogRepository&MockObject $repository;
    private EventLogManager $manager;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(EventLogRepository::class);
        $this->manager = new EventLogManager($this->repository);
    }

    public function testLogCreatesAndPersists(): void
    {
        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(EventLog::class));

        $log = $this->manager->log('dashboard', 'Viewed dashboard');

        $this->assertInstanceOf(EventLog::class, $log);
        $this->assertSame('dashboard', $log->getPage());
        $this->assertSame('Viewed dashboard', $log->getEntry());
        $this->assertNotNull($log->getEntered());
        $this->assertInstanceOf(DateTimeImmutable::class, $log->getEntered());
    }

    public function testDelete(): void
    {
        $log = new EventLog();
        $this->repository->expects($this->once())
            ->method('remove')
            ->with($log);

        $this->manager->delete($log);
    }

    public function testGetWithFiltersDelegatesToRepository(): void
    {
        $expected = [new EventLog(), new EventLog()];

        $this->repository->expects($this->once())
            ->method('getFilteredAfterId')
            ->with(
                100,
                25,
                $this->callback(function (EventLogFilter $filter) {
                    // Use getters to validate
                    return method_exists($filter, 'getPage')
                        && $filter->getPage() === 'settings'
                        && $filter->getDateFrom() instanceof DateTimeImmutable
                        && $filter->getDateTo() instanceof DateTimeImmutable
                        && $filter->getDateFrom() <= $filter->getDateTo();
                })
            )
            ->willReturn($expected);

        $from = new DateTimeImmutable('-2 days');
        $to = new DateTimeImmutable('now');
        $result = $this->manager->get(lastId: 100, limit: 25, page: 'settings', dateFrom: $from, dateTo: $to);

        $this->assertSame($expected, $result);
    }

    public function testGetWithoutFiltersDefaults(): void
    {
        $expected = [];

        $this->repository->expects($this->once())
            ->method('getFilteredAfterId')
            ->with(
                0,
                50,
                $this->anything()
            )
            ->willReturn($expected);

        $result = $this->manager->get();
        $this->assertSame($expected, $result);
    }
}
