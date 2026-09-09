<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Common\Model;

use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;

/** @template-covariant T of DomainModel */
class PaginatedResult
{
    /** @var list<T> */
    private array $items;
    private int $total;
    private int $limit;
    // maybe $lastId not needed
    private int $lastId;

    /** @param list<T> $items */
    public function __construct(
        array $items,
        int $total,
        int $limit,
        int $lastId,
    ) {
        $this->items = $items;
        $this->total = $total;
        $this->limit = $limit;
        $this->lastId = $lastId;
    }

    /** @return  list<T> */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getLastId(): int
    {
        return $this->lastId;
    }
}
