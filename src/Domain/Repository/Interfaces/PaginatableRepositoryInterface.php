<?php

namespace PhpList\Core\Domain\Repository\Interfaces;

namespace PhpList\Core\Domain\Repository\Interfaces;

interface PaginatableRepositoryInterface
{
    public function getAfterId(int $afterId, int $limit): array;
    public function count(): int;
}
