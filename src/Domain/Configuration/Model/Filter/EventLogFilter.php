<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Configuration\Model\Filter;

use DateTimeInterface;
use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;

class EventLogFilter implements FilterRequestInterface
{
    public function __construct(
        private readonly ?string $page = null,
        private readonly ?DateTimeInterface $dateFrom = null,
        private readonly ?DateTimeInterface $dateTo = null,
    ) {
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
