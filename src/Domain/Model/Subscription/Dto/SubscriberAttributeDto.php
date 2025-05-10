<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Subscription\Dto;

class SubscriberAttributeDto
{
    public function __construct(
        public readonly int $subscriberId,
        public readonly int $attributeDefinitionId,
        public readonly string $value,
    ) {
    }
}
