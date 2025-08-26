<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Manager;

use DateTimeImmutable;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Repository\BounceRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class BounceManagerTest extends TestCase
{
    private BounceRepository&MockObject $repository;
    private UserMessageBounceRepository&MockObject $userMessageBounceRepository;
    private BounceManager $manager;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(BounceRepository::class);
        $this->userMessageBounceRepository = $this->createMock(UserMessageBounceRepository::class);
        $this->manager = new BounceManager($this->repository, $this->userMessageBounceRepository);
    }

    public function testCreatePersistsAndReturnsBounce(): void
    {
        $date = new DateTimeImmutable('2020-01-01 00:00:00');
        $header = 'X-Test: Header';
        $data = 'raw bounce';
        $status = 'new';
        $comment = 'created by test';

        $this->repository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Bounce::class));

        $bounce = $this->manager->create(
            date: $date,
            header: $header,
            data: $data,
            status: $status,
            comment: $comment
        );

        $this->assertInstanceOf(Bounce::class, $bounce);
        $this->assertSame( $date->format('Y-m-d h:m:s'), $bounce->getDate()->format('Y-m-d h:m:s'));
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
}
