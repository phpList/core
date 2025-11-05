<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Resolver;

use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Service\Provider\AttributeValueProvider;

class AttributeValueResolver
{
    /** @param iterable<AttributeValueProvider> $providers */
    public function __construct(private readonly iterable $providers)
    {
    }

    public function resolve(SubscriberAttributeValue $userAttr): string
    {
        foreach ($this->providers as $provider) {
            if ($provider->supports($userAttr->getAttributeDefinition())) {
                return $provider->getValue($userAttr->getAttributeDefinition(), $userAttr);
            }
        }
        return '';
    }
}
