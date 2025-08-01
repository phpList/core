<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Common\Repository;

use Doctrine\ORM\QueryBuilder;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;

/**
 * Dummy repository that uses the trait
 */
class DummyRepository
{
    use CursorPaginationTrait;

    public function __construct(private readonly QueryBuilder $queryBuilder)
    {
    }

    /** Doctrine normally injects the QB through $this->createQueryBuilder(). */
    protected function createQueryBuilder(string $alias): QueryBuilder
    {
        return $this->queryBuilder;
    }
}
