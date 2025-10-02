<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Resolver;

use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Service\Provider\AttributeValueProvider;

class AttributeValueResolver
{
    /** @param iterable<AttributeValueProvider> $providers */
    public function __construct(private readonly iterable $providers) {}

    public function resolve(SubscriberAttributeDefinition $attribute, SubscriberAttributeValue $userAttr): string
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($attribute)) {
                return $provider->getValue($attribute, $userAttr);
            }
        }
        return '';
    }
}
