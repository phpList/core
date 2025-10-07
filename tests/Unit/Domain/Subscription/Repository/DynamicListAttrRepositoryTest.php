<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Result;
use InvalidArgumentException;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;
use PHPUnit\Framework\TestCase;

class DynamicListAttrRepositoryTest extends TestCase
{
    public function testFetchOptionNamesReturnsEmptyForEmptyIds(): void
    {
        $conn = $this->createMock(Connection::class);
        $repo = new DynamicListAttrRepository($conn, 'phplist_');

        $this->assertSame([], $repo->fetchOptionNames('valid_table', []));
        $this->assertSame([], $repo->fetchOptionNames('valid_table', []));
    }

    public function testFetchOptionNamesThrowsOnInvalidTable(): void
    {
        $conn = $this->createMock(Connection::class);
        $repo = new DynamicListAttrRepository($conn, 'phplist_');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid list table');

        $repo->fetchOptionNames('invalid-table;', [1, 2]);
    }

    public function testFetchOptionNamesReturnsNames(): void
    {
        $conn = $this->createMock(Connection::class);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'from', 'where', 'setParameter', 'executeQuery'])
            ->getMock();

        $qb->expects($this->once())
            ->method('select')
            ->with('name')
            ->willReturnSelf();

        $qb->expects($this->once())
            ->method('from')
            ->with('phplist_listattr_users')
            ->willReturnSelf();

        $qb->expects($this->once())
            ->method('where')
            ->with('id IN (:ids)')
            ->willReturnSelf();

        // Expect integer coercion of IDs and correct array parameter type
        $qb->expects($this->once())
            ->method('setParameter')
            ->with(
                'ids',
                [1, 2, 3],
                ArrayParameterType::INTEGER
            )
            ->willReturnSelf();

        // Mock Result
        $result = $this->createMock(Result::class);
        $result->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['alpha', 'beta', 'gamma']);

        $qb->expects($this->once())
            ->method('executeQuery')
            ->willReturn($result);

        $conn->method('createQueryBuilder')->willReturn($qb);

        $repo = new DynamicListAttrRepository($conn, 'phplist_');
        $names = $repo->fetchOptionNames('users', [1, '2', 3]);

        $this->assertSame(['alpha', 'beta', 'gamma'], $names);
    }

    public function testFetchSingleOptionNameThrowsOnInvalidTable(): void
    {
        $conn = $this->createMock(Connection::class);
        $repo = new DynamicListAttrRepository($conn, 'phplist_');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid list table');

        $repo->fetchSingleOptionName('bad name!', 10);
    }

    public function testFetchSingleOptionNameReturnsString(): void
    {
        $conn = $this->createMock(Connection::class);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'from', 'where', 'setParameter', 'executeQuery'])
            ->getMock();

        $qb->expects($this->once())->method('select')->with('name')->willReturnSelf();
        $qb->expects($this->once())->method('from')->with('phplist_listattr_ukcountries')->willReturnSelf();
        $qb->expects($this->once())->method('where')->with('id = :id')->willReturnSelf();
        $qb->expects($this->once())->method('setParameter')->with('id', 42)->willReturnSelf();

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchOne')->willReturn('Bradford');

        $qb->expects($this->once())->method('executeQuery')->willReturn($result);
        $conn->method('createQueryBuilder')->willReturn($qb);

        $repo = new DynamicListAttrRepository($conn, 'phplist_');
        $this->assertSame('Bradford', $repo->fetchSingleOptionName('ukcountries', 42));
    }

    public function testFetchSingleOptionNameReturnsNullWhenNotFound(): void
    {
        $conn = $this->createMock(Connection::class);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select', 'from', 'where', 'setParameter', 'executeQuery'])
            ->getMock();

        $qb->method('select')->with('name')->willReturnSelf();
        $qb->method('from')->with('phplist_listattr_termsofservices')->willReturnSelf();
        $qb->method('where')->with('id = :id')->willReturnSelf();
        $qb->method('setParameter')->with('id', 999)->willReturnSelf();

        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchOne')->willReturn(false);

        $qb->method('executeQuery')->willReturn($result);
        $conn->method('createQueryBuilder')->willReturn($qb);

        $repo = new DynamicListAttrRepository($conn, 'phplist_');
        $this->assertNull($repo->fetchSingleOptionName('termsofservices', 999));
    }
}
