<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Validator;

use PhpList\Core\Domain\Common\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Common\Validator\AbstractAttributeTypeValidator;

class AttributeTypeValidator extends AbstractAttributeTypeValidator
{
    private const VALID_TYPES = [
        AttributeTypeEnum::TextLine,
        AttributeTypeEnum::Hidden,
        AttributeTypeEnum::CreditCardNo,
        AttributeTypeEnum::Select,
        AttributeTypeEnum::Date,
        AttributeTypeEnum::Checkbox,
        AttributeTypeEnum::TextArea,
        AttributeTypeEnum::Radio,
        AttributeTypeEnum::CheckboxGroup,
    ];

    protected function getValidTypes(): array
    {
        return self::VALID_TYPES;
    }
}