<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Model\Dto\DomainThrottleReservation;
use PhpList\Core\Domain\Messaging\Repository\DomainThrottleStateRepository;
use PhpList\Core\Domain\Messaging\Service\DomainRateLimiter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class DomainRateLimiterTest extends TestCase
{
    private DomainThrottleStateRepository|MockObject $repository;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(DomainThrottleStateRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    private function createLimiter(
        bool $enabled = true,
        int $domainBatchSize = 1,
        int $domainBatchPeriod = 120,
        bool $autoThrottle = false,
    ): DomainRateLimiter {
        return new DomainRateLimiter(
            repository: $this->repository,
            logger: $this->logger,
            enabled: $enabled,
            domainBatchSize: $domainBatchSize,
            domainBatchPeriod: $domainBatchPeriod,
            autoThrottle: $autoThrottle,
        );
    }

    public function testAllowsSendsWhenDisabled(): void
    {
        $this->repository->expects($this->never())->method('tryReserveSlot');

        $limiter = $this->createLimiter(enabled: false);

        $this->assertTrue($limiter->attemptSend('a@example.com')->allowed);
    }

    public function testAllowsSendsWhenBatchSizeOrPeriodIsNotPositive(): void
    {
        $this->repository->expects($this->never())->method('tryReserveSlot');

        $limiter = $this->createLimiter(domainBatchSize: 0);
        $this->assertTrue($limiter->attemptSend('a@example.com')->allowed);

        $limiter = $this->createLimiter(domainBatchPeriod: 0);
        $this->assertTrue($limiter->attemptSend('a@example.com')->allowed);
    }

    public function testAllowsSendsWhenAddressHasNoAtSign(): void
    {
        $this->repository->expects($this->never())->method('tryReserveSlot');

        $limiter = $this->createLimiter();

        $this->assertTrue($limiter->attemptSend('not-an-email')->allowed);
    }

    public function testDelegatesReservationToRepositoryUsingLowercasedDomain(): void
    {
        $this->repository->expects($this->once())
            ->method('tryReserveSlot')
            ->with('example.com', $this->isType('int'), 1)
            ->willReturn(new DomainThrottleReservation(allowed: true));

        $limiter = $this->createLimiter();
        $result = $limiter->attemptSend('first@Example.COM');

        $this->assertTrue($result->allowed);
        $this->assertSame('example.com', $result->domain);
    }

    public function testReturnsBlockedResultWithAttemptsWhenQuotaReached(): void
    {
        $this->repository->method('tryReserveSlot')
            ->willReturn(new DomainThrottleReservation(allowed: false, blockedAttempts: 3));

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Send blocked by domain throttle', $this->anything());

        $limiter = $this->createLimiter();
        $result = $limiter->attemptSend('third@example.com');

        $this->assertFalse($result->allowed);
        $this->assertSame(3, $result->blockedAttempts);
        $this->assertFalse($result->backoffApplied);
    }

    public function testDoesNotBackoffWhenAutoThrottleDisabled(): void
    {
        $this->repository->method('tryReserveSlot')
            ->willReturn(new DomainThrottleReservation(allowed: false, blockedAttempts: 999));
        $this->repository->expects($this->never())->method('resetBlockedCount');

        $limiter = $this->createLimiter(autoThrottle: false);
        $result = $limiter->attemptSend('third@example.com');

        $this->assertFalse($result->backoffApplied);
    }

    public function testDoesNotBackoffBelowAttemptThreshold(): void
    {
        $this->repository->method('tryReserveSlot')
            ->willReturn(new DomainThrottleReservation(allowed: false, blockedAttempts: 5));
        $this->repository->expects($this->never())->method('resetBlockedCount');

        $limiter = $this->createLimiter(autoThrottle: true);
        $result = $limiter->attemptSend('third@example.com');

        $this->assertFalse($result->backoffApplied);
    }

    public function testAppliesBackoffAndResetsBlockedCountOnceThresholdExceeded(): void
    {
        $this->repository->method('tryReserveSlot')
            ->willReturn(new DomainThrottleReservation(allowed: false, blockedAttempts: 26));
        $this->repository->expects($this->once())
            ->method('resetBlockedCount')
            ->with('example.com', $this->isType('int'), 25)
            ->willReturn(true);

        // Small batch period/size keeps the resulting sleep() short (~1s) so the test stays fast.
        $limiter = $this->createLimiter(domainBatchSize: 1, domainBatchPeriod: 4, autoThrottle: true);
        $result = $limiter->attemptSend('third@example.com');

        $this->assertFalse($result->allowed);
        $this->assertTrue($result->backoffApplied);
        $this->assertGreaterThanOrEqual(1, $result->backoffSeconds);
    }

    public function testDoesNotBackoffWhenLosingTheResetRaceToAnotherWorker(): void
    {
        $this->repository->method('tryReserveSlot')
            ->willReturn(new DomainThrottleReservation(allowed: false, blockedAttempts: 26));
        $this->repository->expects($this->once())
            ->method('resetBlockedCount')
            ->willReturn(false);

        $limiter = $this->createLimiter(domainBatchSize: 1, domainBatchPeriod: 4, autoThrottle: true);
        $result = $limiter->attemptSend('third@example.com');

        $this->assertFalse($result->allowed);
        $this->assertFalse($result->backoffApplied);
        $this->assertSame(0, $result->backoffSeconds);
    }
}
