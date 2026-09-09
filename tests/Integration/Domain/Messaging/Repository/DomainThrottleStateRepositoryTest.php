<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Messaging\Repository;

use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Messaging\Repository\DomainThrottleStateRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DomainThrottleStateRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private DomainThrottleStateRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->repository = self::getContainer()->get(DomainThrottleStateRepository::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testFirstReservationForNewDomainIsAllowed(): void
    {
        $reservation = $this->repository->tryReserveSlot('example.com', 1000, 1);

        $this->assertTrue($reservation->allowed);
        $this->assertSame(0, $reservation->blockedAttempts);
    }

    public function testReservationBlockedOnceQuotaReachedInSameWindow(): void
    {
        $this->assertTrue($this->repository->tryReserveSlot('example.com', 1000, 1)->allowed);

        $second = $this->repository->tryReserveSlot('example.com', 1000, 1);

        $this->assertFalse($second->allowed);
        $this->assertSame(1, $second->blockedAttempts);

        $third = $this->repository->tryReserveSlot('example.com', 1000, 1);
        $this->assertFalse($third->allowed);
        $this->assertSame(2, $third->blockedAttempts);
    }

    public function testDomainsAreTrackedIndependently(): void
    {
        $this->assertTrue($this->repository->tryReserveSlot('a.com', 1000, 1)->allowed);

        $this->assertTrue($this->repository->tryReserveSlot('b.com', 1000, 1)->allowed);
        $this->assertFalse($this->repository->tryReserveSlot('a.com', 1000, 1)->allowed);
    }

    public function testReservationAllowedAgainAfterWindowRollsOver(): void
    {
        $this->assertTrue($this->repository->tryReserveSlot('example.com', 1000, 1)->allowed);
        $this->assertFalse($this->repository->tryReserveSlot('example.com', 1000, 1)->allowed);

        $nextWindow = $this->repository->tryReserveSlot('example.com', 1120, 1);

        $this->assertTrue($nextWindow->allowed);
    }

    public function testResetBlockedCountClearsCounterForCurrentWindow(): void
    {
        $this->repository->tryReserveSlot('example.com', 1000, 1);
        $this->repository->tryReserveSlot('example.com', 1000, 1);
        $blocked = $this->repository->tryReserveSlot('example.com', 1000, 1);
        $this->assertSame(2, $blocked->blockedAttempts);

        $claimed = $this->repository->resetBlockedCount('example.com', 1000, threshold: 1);
        $this->assertTrue($claimed);

        $afterReset = $this->repository->tryReserveSlot('example.com', 1000, 1);
        $this->assertFalse($afterReset->allowed);
        $this->assertSame(1, $afterReset->blockedAttempts);
    }

    public function testResetBlockedCountDoesNotClaimWhenCountAtOrBelowThreshold(): void
    {
        $this->repository->tryReserveSlot('example.com', 1000, 1);
        $this->repository->tryReserveSlot('example.com', 1000, 1);
        $blocked = $this->repository->tryReserveSlot('example.com', 1000, 1);
        $this->assertSame(2, $blocked->blockedAttempts);

        $claimed = $this->repository->resetBlockedCount('example.com', 1000, threshold: 2);
        $this->assertFalse($claimed);

        $afterAttempt = $this->repository->tryReserveSlot('example.com', 1000, 1);
        $this->assertFalse($afterAttempt->allowed);
        $this->assertSame(3, $afterAttempt->blockedAttempts);
    }
}
