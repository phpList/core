<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Messaging\Model\Bounce;

class BounceRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    /** @return Bounce[] */
    public function findByStatus(string $status): array
    {
        return $this->findBy(['status' => $status]);
    }
}
