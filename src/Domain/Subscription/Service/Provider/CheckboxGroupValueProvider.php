<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Common\Model\AttributeTypeEnum;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;

class CheckboxGroupValueProvider implements AttributeValueProvider
{
    public function __construct(private readonly DynamicListAttrRepository $repo)
    {
    }

    public function supports(SubscriberAttributeDefinition $attribute): bool
    {
        return $attribute->getType() === AttributeTypeEnum::CheckboxGroup;
    }

    public function getValue(SubscriberAttributeDefinition $attribute, SubscriberAttributeValue $userValue): string
    {
        $csv = $userValue->getValue() ?? '';
        if ($csv === '') {
            return '';
        }

        $ids = array_values(array_filter(array_map(function ($value) {
            $index = (int) trim($value);
            return $index > 0 ? $index : null;
        }, explode(',', $csv))));

        if (empty($ids) || !$attribute->getTableName()) {
            return '';
        }

        $names = $this->repo->fetchOptionNames($attribute->getTableName(), $ids);

        return implode('; ', $names);
    }
}
