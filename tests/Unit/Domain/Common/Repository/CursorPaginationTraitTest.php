<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common\Repository;

use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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

        self::assertSame($expected, $result->getItems());
    }

    public function testGetFilteredAfterIdDelegates(): void
    {
        $expected = ['cursor', 'pagination'];
        $this->query->method('getResult')->willReturn($expected);
        $dummyFilter = $this->createMock(FilterRequestInterface::class);
        $dummyFilter->method('getLastId')->willReturn(10);
        $dummyFilter->method('getLimit')->willReturn(2);

        $result = $this->repo->getFilteredAfterId($dummyFilter);

        self::assertSame($expected, $result->getItems());
    }

    public function testGetFilteredAfterIdUsesFilterPaginationValues(): void
    {
        $dummyFilter = $this->createMock(FilterRequestInterface::class);
        $dummyFilter->method('getLastId')->willReturn(7);
        $dummyFilter->method('getLimit')->willReturn(3);

        $this->qb
            ->expects(self::once())
            ->method('setParameter')
            ->with('lastId', 7)
            ->willReturnSelf();
        $this->qb
            ->expects(self::once())
            ->method('setMaxResults')
            ->with(3)
            ->willReturnSelf();
        $this->query->method('getResult')->willReturn([]);

        $this->repo->getFilteredAfterId($dummyFilter);
    }
}
