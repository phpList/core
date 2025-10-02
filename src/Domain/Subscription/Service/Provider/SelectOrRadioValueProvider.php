<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\DynamicListAttrRepository;

class SelectOrRadioValueProvider implements AttributeValueProvider
{
    public function __construct(private readonly DynamicListAttrRepository $repo) {}

    public function supports(SubscriberAttributeDefinition $attribute): bool
    {
        return \in_array($attribute->getType(), ['select','radio'], true);
    }

    public function getValue(SubscriberAttributeDefinition $attribute, SubscriberAttributeValue $userValue): string
    {
        if (!$attribute->getTableName()) return '';

        $id = (int)($userValue->getValue() ?? 0);
        if ($id <= 0) {
            return '';
        }

        return $this->repo->fetchSingleOptionName($attribute->getTableName(), $id) ?? '';
    }
}
