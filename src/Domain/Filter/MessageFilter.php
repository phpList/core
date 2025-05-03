<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Filter;

use PhpList\Core\Domain\Model\Identity\Administrator;

class MessageFilter implements FilterRequestInterface
{
    private ?Administrator $owner = null;

    public function getOwner(): ?Administrator
    {
        return $this->owner;
    }

    public function setOwner(?Administrator $admin): self
    {
        $this->owner = $admin;
        return $this;
    }
}
