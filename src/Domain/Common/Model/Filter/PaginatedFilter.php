<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model\Filter;

use InvalidArgumentException;

class PaginatedFilter implements FilterRequestInterface
{
    private int $lastId = 0;
    private int $limit = 50;

    public function setLastId(int $lastId): self
    {
        $this->lastId = $lastId;

        return $this;
    }

    public function getLastId(): int
    {
        return $this->lastId;
    }

    public function setLimit(int $limit): self
    {
        if ($limit <= 0) {
            throw new InvalidArgumentException('Limit must be greater than 0.');
        }

        $this->limit = $limit;

        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
