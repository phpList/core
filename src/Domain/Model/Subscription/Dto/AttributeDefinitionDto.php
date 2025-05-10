<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Model\Subscription\Dto;

class AttributeDefinitionDto
{
    public string $name;
    public ?string $type = null;
    public ?int $listOrder = null;
    public ?string $defaultValue = null;
    public ?bool $required = null;
    public ?string $tableName = null;
}
