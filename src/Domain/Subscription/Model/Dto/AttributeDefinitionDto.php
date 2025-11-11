<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Model\Dto;

use InvalidArgumentException;
use PhpList\Core\Domain\Subscription\Model\AttributeTypeEnum;

class AttributeDefinitionDto
{
    /**
     * @SuppressWarnings("BooleanArgumentFlag")
     * @param DynamicListAttrDto[] $options
     * @throws InvalidArgumentException
     */
    public function __construct(
        public readonly string $name,
        public readonly ?AttributeTypeEnum $type = null,
        public readonly ?int $listOrder = null,
        public readonly ?string $defaultValue = null,
        public readonly ?bool $required = false,
        public readonly array $options = [],
    ) {
        if (trim($this->name) === '') {
            throw new InvalidArgumentException('Name cannot be empty');
        }
    }
}
