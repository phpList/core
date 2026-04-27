<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Filter;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\Filter\PaginatedFilter;

class BounceFilter extends PaginatedFilter implements FilterRequestInterface
{
    private ?int $listId = null;
    private ?string $status = null;

    public function getListId(): ?int
    {
        return $this->listId;
    }

    public function setListId(?int $listId): self
    {
        $this->listId = $listId;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        if ($status !== null) {
            $status = trim($status);
        }
        $this->status = $status;
        return $this;
    }
}
