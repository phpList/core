<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;

class ScalarValueProvider implements AttributeValueProvider
{
    public function supports(SubscriberAttributeDefinition $attribute): bool
    {
        return $attribute->getType() === null;
    }

    public function getValue(SubscriberAttributeDefinition $attribute, SubscriberAttributeValue $userValue): string
    {
        return $userValue->getValue() ?? '';
    }
}
