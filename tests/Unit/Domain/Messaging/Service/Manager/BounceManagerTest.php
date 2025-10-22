<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Manager;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Bounce\Service\Manager\BounceManager;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Repository\BounceRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Translation\Translator;

class BounceManagerTest extends TestCase
{
    private BounceRepository&MockObject $repository;
    private UserMessageBounceRepository&MockObject $userMessageBounceRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private LoggerInterface&MockObject $logger;
    private BounceManager $manager;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(BounceRepository::class);
        $this->userMessageBounceRepository = $this->createMock(UserMessageBounceRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->manager = new BounceManager(
            bounceRepository: $this->repository,
            userMessageBounceRepo: $this->userMessageBounceRepository,
            entityManager: $this->entityManager,
            logger: $this->logger,
            translator: new Translator('en')
        );
    }

    public function testCreatePersistsAndReturnsBounce(): void
    {
        $date = new DateTimeImmutable('2020-01-01 00:00:00');
        $header = 'X-Test: Header';
        $data = 'raw bounce';
        $status = 'new';
        $comment = 'created by test';

        $this->repository->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Bounce::class));

        $bounce = $this->manager->create(
            date: $date,
            header: $header,
            data: $data,
            status: $status,
            comment: $comment
        );

        $this->assertInstanceOf(Bounce::class, $bounce);
        $this->assertSame($date->format('Y-m-d h:m:s'), $bounce->getDate()->format('Y-m-d h:m:s'));
        $this->assertSame($header, $bounce->getHeader());
        $this->assertSame($data, $bounce->getData());
        $this->assertSame($status, $bounce->getStatus());
        $this->assertSame($comment, $bounce->getComment());
    }

    public function testDeleteDelegatesToRepository(): void
    {
        $model = new Bounce();

        $this->repository->expects($this->once())
            ->method('remove')
            ->with($model);

        $this->manager->delete($model);
    }

    public function testGetAllReturnsArray(): void
    {
        $expected = [new Bounce(), new Bounce()];

        $this->repository->expects($this->once())
            ->method('findAll')
            ->willReturn($expected);

        $this->assertSame($expected, $this->manager->getAll());
    }

    public function testGetByIdReturnsBounce(): void
    {
        $expected = new Bounce();

        $this->repository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($expected);

        $this->assertSame($expected, $this->manager->getById(123));
    }

    public function testGetByIdReturnsNullWhenNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->assertNull($this->manager->getById(999));
    }

    public function testUpdateChangesFieldsAndSaves(): void
    {
        $bounce = new Bounce();
        $this->entityManager->expects($this->once())
            ->method('flush');

        $updated = $this->manager->update($bounce, 'processed', 'done');
        $this->assertSame($bounce, $updated);
        $this->assertSame('processed', $bounce->getStatus());
        $this->assertSame('done', $bounce->getComment());
    }

    public function testLinkUserMessageBounceFlushesAndSetsFields(): void
    {
        $bounce = $this->createMock(Bounce::class);
        $bounce->method('getId')->willReturn(77);

        $dt = new DateTimeImmutable('2024-05-01 12:34:56');
        $umb = $this->manager->linkUserMessageBounce($bounce, $dt, 123, 456);

        $this->assertSame(77, $umb->getBounceId());
        $this->assertSame(123, $umb->getUserId());
        $this->assertSame(456, $umb->getMessageId());
    }

    public function testExistsUserMessageBounceDelegatesToRepo(): void
    {
        $this->userMessageBounceRepository->expects($this->once())
            ->method('existsByMessageIdAndUserId')
            ->with(456, 123)
            ->willReturn(true);

        $this->assertTrue($this->manager->existsUserMessageBounce(123, 456));
    }

    public function testFindByStatusDelegatesToRepository(): void
    {
        $b1 = new Bounce();
        $b2 = new Bounce();
        $this->repository->expects($this->once())
            ->method('findByStatus')
            ->with('new')
            ->willReturn([$b1, $b2]);

        $this->assertSame([$b1, $b2], $this->manager->findByStatus('new'));
    }

    public function testGetUserMessageBounceCount(): void
    {
        $this->userMessageBounceRepository->expects($this->once())
            ->method('count')
            ->willReturn(5);
        $this->assertSame(5, $this->manager->getUserMessageBounceCount());
    }

    public function testFetchUserMessageBounceBatchDelegates(): void
    {
        $expected = [['umb' => new UserMessageBounce(1, new \DateTime()), 'bounce' => new Bounce()]];
        $this->userMessageBounceRepository->expects($this->once())
            ->method('getPaginatedWithJoinNoRelation')
            ->with(10, 50)
            ->willReturn($expected);
        $this->assertSame($expected, $this->manager->fetchUserMessageBounceBatch(10, 50));
    }

    public function testGetUserMessageHistoryWithBouncesDelegates(): void
    {
        $subscriber = new Subscriber();
        $expected = [];
        $this->userMessageBounceRepository->expects($this->once())
            ->method('getUserMessageHistoryWithBounces')
            ->with($subscriber)
            ->willReturn($expected);
        $this->assertSame($expected, $this->manager->getUserMessageHistoryWithBounces($subscriber));
    }

    public function testAnnounceDeletionModeLogsCorrectMessage(): void
    {
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->withConsecutive([
                'Running in test mode, not deleting messages from mailbox'
            ], [
                'Processed messages will be deleted from the mailbox'
            ]);

        $this->manager->announceDeletionMode(true);
        $this->manager->announceDeletionMode(false);
    }
}
