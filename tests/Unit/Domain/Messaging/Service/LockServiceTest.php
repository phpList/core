<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Model\SendProcess;
use PhpList\Core\Domain\Messaging\Repository\SendProcessRepository;
use PhpList\Core\Domain\Messaging\Service\LockService;
use PhpList\Core\Domain\Messaging\Service\Manager\SendProcessManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LockServiceTest extends TestCase
{
    private SendProcessRepository&MockObject $repo;
    private SendProcessManager&MockObject $manager;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        $this->repo = $this->createMock(SendProcessRepository::class);
        $this->manager = $this->createMock(SendProcessManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testAcquirePageLockCreatesProcessWhenBelowMax(): void
    {
        $service = new LockService($this->repo, $this->manager, $this->logger, 600, 0, 0);

        $this->repo->method('countAliveByPage')->willReturn(0);
        $this->manager->method('findNewestAliveWithAge')->willReturn(null);

        $sendProcess = $this->createConfiguredMock(SendProcess::class, ['getId' => 42]);
        $this->manager->expects($this->once())
            ->method('create')
            ->with('mypage', $this->callback(fn(string $id) => $id !== ''))
            ->willReturn($sendProcess);

        $id = $service->acquirePageLock('my page');
        $this->assertSame(42, $id);
    }

    public function testAcquirePageLockReturnsNullWhenAtMaxInCli(): void
    {
        $service = new LockService($this->repo, $this->manager, $this->logger, 600, 0, 0);

        $this->repo->method('countAliveByPage')->willReturn(1);
        $this->manager->method('findNewestAliveWithAge')->willReturn(['age' => 1, 'id' => 10]);

        $this->logger->expects($this->atLeastOnce())->method('info');
        $id = $service->acquirePageLock('page', false, true, false, 1);
        $this->assertNull($id);
    }

    public function testAcquirePageLockStealsStale(): void
    {
        $service = new LockService($this->repo, $this->manager, $this->logger, 1, 0, 0);

        $this->repo->expects($this->exactly(2))->method('countAliveByPage')->willReturnOnConsecutiveCalls(1, 0);
        $this->manager
            ->expects($this->exactly(2))
            ->method('findNewestAliveWithAge')
            ->willReturnOnConsecutiveCalls(['age' => 5, 'id' => 10], null);
        $this->repo->expects($this->once())->method('markDeadById')->with(10);

        $sendProcess = $this->createConfiguredMock(SendProcess::class, ['getId' => 99]);
        $this->manager->method('create')->willReturn($sendProcess);

        $id = $service->acquirePageLock('page', false, true);
        $this->assertSame(99, $id);
    }

    public function testKeepCheckReleaseDelegatesToRepo(): void
    {
        $service = new LockService($this->repo, $this->manager, $this->logger);

        $this->repo->expects($this->once())->method('incrementAlive')->with(5);
        $service->keepLock(5);

        $this->repo->expects($this->once())->method('getAliveValue')->with(5)->willReturn(7);
        $this->assertSame(7, $service->checkLock(5));

        $this->repo->expects($this->once())->method('markDeadById')->with(5);
        $service->release(5);
    }
}
