<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Repository;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Identity\Model\AdminAttributeDefinition;
use PhpList\Core\Domain\Identity\Model\AdminAttributeValue;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Repository\AdminAttributeValueRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Testcase for the AdminAttributeValueRepository.
 */
class AdminAttributeValueRepositoryTest extends TestCase
{
    private AdminAttributeValueRepository $subject;
    private EntityManager&MockObject $entityManager;
    private QueryBuilder&MockObject $queryBuilder;
    private Query&MockObject $query;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManager::class);
        $classMetadata = $this->createMock(ClassMetadata::class);
        $classMetadata->name = AdminAttributeValue::class;

        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);

        $this->subject = new AdminAttributeValueRepository($this->entityManager, $classMetadata);
    }

    public function testIsAbstractRepository(): void
    {
        self::assertInstanceOf(AbstractRepository::class, $this->subject);
    }

    public function testFindOneByAdminIdAndAttributeId(): void
    {
        $adminId = 1;
        $attributeId = 2;

        $attributeDefinition = $this->createMock(AdminAttributeDefinition::class);
        $attributeDefinition->method('getId')->willReturn($attributeId);

        $administrator = $this->createMock(Administrator::class);
        $administrator->method('getId')->willReturn($adminId);

        $expectedResult = new AdminAttributeValue(
            $attributeDefinition,
            $administrator,
            'value'
        );

        $this->entityManager->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('select')
            ->with('aav')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('from')
            ->with(AdminAttributeValue::class, 'aav')
            ->willReturn($this->queryBuilder);

        $joinCalls = [];

        $this->queryBuilder->expects($this->exactly(2))
            ->method('join')
            ->willReturnCallback(
                function (string $join, string $alias) use (&$joinCalls) {
                    $joinCalls[] = [$join, $alias];

                    return $this->queryBuilder;
                }
            );

        $this->queryBuilder->expects($this->once())
            ->method('where')
            ->with('admin.id = :adminId')
            ->willReturn($this->queryBuilder);

        $this->queryBuilder->expects($this->once())
            ->method('andWhere')
            ->with('attr.id = :attributeId')
            ->willReturn($this->queryBuilder);

        $setParameterCalls = [];

        $this->queryBuilder->expects($this->exactly(2))
            ->method('setParameter')
            ->willReturnCallback(
                function (string $key, mixed $value) use (&$setParameterCalls) {
                    $setParameterCalls[] = [$key, $value];

                    return $this->queryBuilder;
                }
            );

        $this->queryBuilder->expects($this->once())
            ->method('getQuery')
            ->willReturn($this->query);

        $this->query->expects($this->once())
            ->method('getOneOrNullResult')
            ->willReturn($expectedResult);

        $result = $this->subject->findOneByAdminIdAndAttributeId(
            $adminId,
            $attributeId
        );

        $this->assertSame(
            [
                ['aav.administrator', 'admin'],
                ['aav.attributeDefinition', 'attr'],
            ],
            $joinCalls,
        );

        $this->assertSame(
            [
                ['adminId', $adminId],
                ['attributeId', $attributeId],
            ],
            $setParameterCalls,
        );

        $this->assertSame($expectedResult, $result);
    }
}
