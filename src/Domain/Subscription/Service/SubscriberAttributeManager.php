<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Exception\SubscriberAttributeCreationException;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;

class SubscriberAttributeManager
{
    private SubscriberAttributeValueRepository $attributeRepository;

    public function __construct(SubscriberAttributeValueRepository $attributeRepository)
    {
        $this->attributeRepository = $attributeRepository;
    }

    public function createOrUpdate(
        Subscriber $subscriber,
        SubscriberAttributeDefinition $definition,
        ?string $value = null
    ): SubscriberAttributeValue {
        $subscriberAttribute = $this->attributeRepository
            ->findOneBySubscriberAndAttribute($subscriber, $definition);

        if (!$subscriberAttribute) {
            $subscriberAttribute = new SubscriberAttributeValue($definition, $subscriber);
        }

        $value = $value ?? $definition->getDefaultValue();
        if ($value === null) {
            throw new SubscriberAttributeCreationException('Value is required', 400);
        }

        $subscriberAttribute->setValue($value);
        $this->attributeRepository->save($subscriberAttribute);

        return $subscriberAttribute;
    }

    public function getSubscriberAttribute(int $subscriberId, int $attributeDefinitionId): SubscriberAttributeValue
    {
        return $this->attributeRepository->findOneBySubscriberIdAndAttributeId($subscriberId, $attributeDefinitionId);
    }

    public function delete(SubscriberAttributeValue $attribute): void
    {
        $this->attributeRepository->remove($attribute);
    }
}
