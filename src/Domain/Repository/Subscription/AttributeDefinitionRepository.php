<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Repository\Subscription;

use PhpList\Core\Domain\Model\Subscription\AttributeDefinition;
use PhpList\Core\Domain\Repository\AbstractRepository;
use PhpList\Core\Domain\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Repository\Interfaces\PaginatableRepositoryInterface;

class AttributeDefinitionRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function findOneByName(string $name): ?AttributeDefinition
    {
        return $this->findOneBy(['name' => $name]);
    }
}
