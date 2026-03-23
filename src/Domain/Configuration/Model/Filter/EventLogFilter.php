<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model\Filter;

use DateTimeInterface;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\Filter\PaginatedFilterTrait;

class EventLogFilter implements FilterRequestInterface
{
    use PaginatedFilterTrait;

    public function __construct(
        private readonly ?string $page = null,
        private readonly ?DateTimeInterface $dateFrom = null,
        private readonly ?DateTimeInterface $dateTo = null,
        int $lastId = 0,
        int $limit = 50,
    ) {
        $this->setLastId($lastId);
        $this->setLimit($limit);
    }

    public function getPage(): ?string
    {
        return $this->page;
    }

    public function getDateFrom(): ?DateTimeInterface
    {
        return $this->dateFrom;
    }

    public function getDateTo(): ?DateTimeInterface
    {
        return $this->dateTo;
    }
}
