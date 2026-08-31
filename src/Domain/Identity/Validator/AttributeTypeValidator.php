<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Validator;

use PhpList\Core\Domain\Common\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Common\Validator\AbstractAttributeTypeValidator;

class AttributeTypeValidator extends AbstractAttributeTypeValidator
{
    private const VALID_TYPES = [
        AttributeTypeEnum::TextLine,
        AttributeTypeEnum::Hidden,
    ];

    protected function getValidTypes(): array
    {
        return self::VALID_TYPES;
    }
}
