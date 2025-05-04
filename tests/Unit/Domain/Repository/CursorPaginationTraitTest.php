<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PhpList\Core\Domain\Filter\FilterRequestInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class CursorPaginationTraitTest extends TestCase
{
    private QueryBuilder|MockObject $qb;
    private Query|MockObject $query;
    private DummyRepository $repo;

    protected function setUp(): void
    {
        $this->qb = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);

        $this->qb->method('andWhere')->willReturnSelf();
        $this->qb->method('setParameter')->willReturnSelf();
        $this->qb->method('orderBy')->willReturnSelf();
        $this->qb->method('setMaxResults')->willReturnSelf();
        $this->qb->method('getQuery')->willReturn($this->query);

        $this->repo  = new DummyRepository($this->qb);
    }

    public function testGetAfterIdReturnsResults(): void
    {
        $expected = ['foo', 'bar'];
        $this->query
            ->expects(self::once())
            ->method('getResult')
            ->willReturn($expected);

        $result = $this->repo->getAfterId(10, 2);

        self::assertSame($expected, $result);
    }

    public function testGetFilteredAfterIdWithNullFilterDelegates(): void
    {
        $expected = ['cursor', 'pagination'];
        // same expectations as previous test
        $this->query->method('getResult')->willReturn($expected);

        $result = $this->repo->getFilteredAfterId(10, 2, null);

        self::assertSame($expected, $result);
    }

    public function testGetFilteredAfterIdWithFilterThrows(): void
    {
        $dummyFilter = $this->createMock(FilterRequestInterface::class);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Filter method not implemented');

        $this->repo->getFilteredAfterId(0, 10, $dummyFilter);
    }
}
