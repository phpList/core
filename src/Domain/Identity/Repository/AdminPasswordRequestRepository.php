<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Repository;

use PhpList\Core\Domain\Common\Repository\AbstractRepository;
use PhpList\Core\Domain\Common\Repository\CursorPaginationTrait;
use PhpList\Core\Domain\Common\Repository\Interfaces\PaginatableRepositoryInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\AdminPasswordRequest;

class AdminPasswordRequestRepository extends AbstractRepository implements PaginatableRepositoryInterface
{
    use CursorPaginationTrait;

    public function findByAdmin(Administrator $administrator): array
    {
        return $this->findBy(['administrator' => $administrator]);
    }

    public function findOneByToken(string $token): ?AdminPasswordRequest
    {
        return $this->findOneBy(['keyValue' => $token]);
    }
}
