<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Filter;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Identity\Model\Administrator;

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
