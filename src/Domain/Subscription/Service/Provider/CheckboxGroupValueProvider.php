<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;

class CheckboxGroupValueProvider implements AttributeValueProvider
{
    public function __construct(private DynamicListAttrRepository $repo) {}

    public function supports(SubscriberAttributeDefinition $attribute): bool
    {
        // todo: check what real types exist in the database
        return $attribute->getType() === 'checkboxgroup';
    }

    public function getValue(SubscriberAttributeDefinition $attribute, SubscriberAttributeValue $userValue): string
    {
        $csv = $userValue->getValue() ?? '';
        if ($csv === '') {
            return '';
        }

        $ids = array_values(array_filter(array_map(
            fn($value) => ($index = (int)trim($value)) > 0 ? $index : null,
            explode(',', $csv)
        )));

        if (empty($ids) || !$attribute->getTableName()) return '';

        $names = $this->repo->fetchOptionNames($attribute->getTableName(), $ids);

        return implode('; ', $names);
    }
}
