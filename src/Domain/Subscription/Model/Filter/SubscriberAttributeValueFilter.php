<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Filter;

use PhpList\Core\Domain\Common\Model\Filter\FilterRequestInterface;

class SubscriberAttributeValueFilter implements FilterRequestInterface
{
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
