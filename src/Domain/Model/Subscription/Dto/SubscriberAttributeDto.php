<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Subscription\Dto;

class SubscriberAttributeDto
{
    public int $subscriberId;
    public int $attributeDefinitionId;
    public string $value;
}
