<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Messaging\Model\Filter;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\Filter\PaginatedFilter;
use PhpList\Core\Domain\Identity\Model\Administrator;

class MessageFilter extends PaginatedFilter implements FilterRequestInterface
{
    private ?Administrator $owner = null;
    private ?string $subject = null;

    public function getOwner(): ?Administrator
    {
        return $this->owner;
    }

    public function setOwner(?Administrator $admin): self
    {
        $this->owner = $admin;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        if ($subject !== null) {
            $subject = trim($subject);
        }
        $this->subject = $subject;
        return $this;
    }
}
