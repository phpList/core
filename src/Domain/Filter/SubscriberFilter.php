<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Filter;

class SubscriberFilter implements FilterRequestInterface
{
    private ?int $listId = null;

    public function setListId(?int $listId): self
    {
        $this->listId = $listId;
        return $this;
    }

    public function getListId(): ?int
    {
        return $this->listId;
    }
}
