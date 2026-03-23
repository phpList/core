<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Filter;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;
use PhpList\Core\Domain\Common\Model\Filter\PaginatedFilterTrait;

class SubscriberAttributeValueFilter implements FilterRequestInterface
{
    use PaginatedFilterTrait;

    private ?int $subscriberId = null;

    public function setSubscriberId(?int $subscriberId): self
    {
        $this->subscriberId = $subscriberId;
        return $this;
    }

    public function getSubscriberId(): ?int
    {
        return $this->subscriberId;
    }
}
