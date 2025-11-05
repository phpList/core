<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;

interface AttributeValueProvider
{
    public function supports(SubscriberAttributeDefinition $attribute): bool;

    /** Return normalized, human-readable value (string) */
    public function getValue(SubscriberAttributeDefinition $attribute, SubscriberAttributeValue $userValue): string;
}
