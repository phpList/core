<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Identity\Model\AdminAttributeDefinition;

class AdminAttributeDefinitionRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function findOneByName(string $name): ?AdminAttributeDefinition
    {
        return $this->findOneBy(['name' => $name]);
    }
}
