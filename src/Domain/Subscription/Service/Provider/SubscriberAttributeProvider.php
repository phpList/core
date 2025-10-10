<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Provider;

use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Service\Resolver\AttributeValueResolver;

class SubscriberAttributeProvider
{
    public function __construct(
        private readonly AttributeValueResolver $resolver,
        private readonly SubscriberAttributeValueRepository $attributesRepository,
    ) {
    }

    public function getMappedValues(Subscriber $subscriber): array
    {
        $userAttributes = $this->attributesRepository->getForSubscriber($subscriber);
        foreach ($userAttributes as $userAttribute) {
            $data[$userAttribute->getAttributeDefinition()->getName()] = $this->resolver->resolve($userAttribute);
        }

        return $data ?? [];
    }
}
