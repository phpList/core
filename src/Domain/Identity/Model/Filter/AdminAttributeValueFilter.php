<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model\Filter;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\Filter\PaginatedFilterTrait;

class AdminAttributeValueFilter implements FilterRequestInterface
{
    use PaginatedFilterTrait;

    private ?int $adminId = null;

    public function setAdminId(?int $adminId): self
    {
        $this->adminId = $adminId;
        return $this;
    }

    public function getAdminId(): ?int
    {
        return $this->adminId;
    }
}
