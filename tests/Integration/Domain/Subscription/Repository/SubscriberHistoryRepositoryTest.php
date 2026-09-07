<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Subscription\Repository;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberHistory;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class SubscriberHistoryRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private SubscriberHistoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->repository = self::getContainer()->get(SubscriberHistoryRepository::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    private function persistHistoryRow(DateTime $date): SubscriberHistory
    {
        $subscriber = new Subscriber('subscriber-' . uniqid('', true) . '@example.com');
        $this->entityManager->persist($subscriber);
        $this->entityManager->flush();

        $history = new SubscriberHistory($subscriber);
        $reflection = new ReflectionProperty(SubscriberHistory::class, 'createdAt');
        $reflection->setAccessible(true);
        $reflection->setValue($history, $date);

        $this->entityManager->persist($history);
        $this->entityManager->flush();

        return $history;
    }

    public function testCountOlderThanOnlyCountsRowsBeforeCutoff(): void
    {
        $old = $this->persistHistoryRow(new DateTime('2020-01-01'));
        $this->persistHistoryRow(new DateTime('2030-01-01'));

        $count = $this->repository->countOlderThan(new DateTime('2025-01-01'));

        self::assertSame(1, $count);
        self::assertNotNull($old->getId());
    }

    public function testFetchBatchOlderThanReturnsOnlyMatchingRowsInIdOrder(): void
    {
        $old1 = $this->persistHistoryRow(new DateTime('2020-01-01'));
        $old2 = $this->persistHistoryRow(new DateTime('2020-06-01'));
        $this->persistHistoryRow(new DateTime('2030-01-01'));

        $batch = [...$this->repository->fetchBatchOlderThan(new DateTime('2025-01-01'), 0, 10)];

        self::assertCount(2, $batch);
        self::assertSame($old1->getId(), $batch[0]->getId());
        self::assertSame($old2->getId(), $batch[1]->getId());
    }

    public function testFetchBatchOlderThanRespectsLastIdCursor(): void
    {
        $old1 = $this->persistHistoryRow(new DateTime('2020-01-01'));
        $old2 = $this->persistHistoryRow(new DateTime('2020-06-01'));

        $batch = [...$this->repository->fetchBatchOlderThan(new DateTime('2025-01-01'), $old1->getId(), 10)];

        self::assertCount(1, $batch);
        self::assertSame($old2->getId(), $batch[0]->getId());
    }

    public function testDeleteByIdsRemovesOnlyGivenRows(): void
    {
        $toDelete = $this->persistHistoryRow(new DateTime('2020-01-01'));
        $toKeep = $this->persistHistoryRow(new DateTime('2020-06-01'));

        $deleted = $this->repository->deleteByIds([$toDelete->getId()]);
        $this->entityManager->clear(); // bulk DQL delete bypasses the identity map

        self::assertSame(1, $deleted);
        self::assertSame(1, $this->repository->countOlderThan(new DateTime('2025-01-01')));
        self::assertNotNull($this->repository->find($toKeep->getId()));
        self::assertNull($this->repository->find($toDelete->getId()));
    }

    public function testDeleteByIdsWithEmptyArrayDeletesNothing(): void
    {
        $this->persistHistoryRow(new DateTime('2020-01-01'));

        $deleted = $this->repository->deleteByIds([]);

        self::assertSame(0, $deleted);
    }
}