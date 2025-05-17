<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Model\Filter;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;

class AdminAttributeValueFilter implements FilterRequestInterface
{
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
