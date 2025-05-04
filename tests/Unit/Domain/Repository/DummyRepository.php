<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Repository;

use Doctrine\ORM\QueryBuilder;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;

/**
 * Dummy repository that uses the trait
 */
class DummyRepository
{
    use CursorPaginationTrait;

    public function __construct(private readonly QueryBuilder $queryBuilder)
    {
    }

    public function getAlias(): string
    {
        return 'dummy';
    }

    /** Doctrine normally injects the QB through $this->createQueryBuilder(). */
    protected function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->queryBuilder;
    }
}
