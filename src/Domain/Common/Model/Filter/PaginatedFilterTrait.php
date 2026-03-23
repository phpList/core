<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model\Filter;

trait PaginatedFilterTrait
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
        $this->limit = $limit;

        return $this;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }
}
