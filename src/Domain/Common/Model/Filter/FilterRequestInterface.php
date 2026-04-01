<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model\Filter;

interface FilterRequestInterface
{
    public function getLastId(): int;
    public function getLimit(): int;
    public function setLastId(int $lastId): self;
    public function setLimit(int $limit): self;
}
