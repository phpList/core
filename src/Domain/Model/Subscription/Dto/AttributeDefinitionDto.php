<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Subscription\Dto;

class AttributeDefinitionDto
{
    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $type = null,
        public readonly ?int $listOrder = null,
        public readonly ?string $defaultValue = null,
        public readonly ?bool $required = false,
        public readonly ?string $tableName = null,
    ) {
    }
}
