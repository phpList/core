<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Exception\SubscriberAttributeCreationException;
use PhpList\Core\Domain\Subscription\Model\Dto\SubscriberAttributeDto;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;

class SubscriberAttributeManager
{
    private SubscriberAttributeDefinitionRepository $definitionRepository;
    private SubscriberAttributeValueRepository $attributeRepository;
    private SubscriberRepository $subscriberRepository;

    public function __construct(
        SubscriberAttributeDefinitionRepository $definitionRepository,
        SubscriberAttributeValueRepository$attributeRepository,
        SubscriberRepository $subscriberRepository,
    ) {
        $this->definitionRepository = $definitionRepository;
        $this->attributeRepository = $attributeRepository;
        $this->subscriberRepository = $subscriberRepository;
    }

    public function createOrUpdate(SubscriberAttributeDto $dto): SubscriberAttributeValue
    {
        $subscriber = $this->subscriberRepository->find($dto->subscriberId);
        if (!$subscriber) {
            throw new SubscriberAttributeCreationException('Subscriber does not exist', 404);
        }

        $attributeDefinition = $this->definitionRepository->find($dto->attributeDefinitionId);
        if (!$attributeDefinition) {
            throw new SubscriberAttributeCreationException('Attribute definition does not exist', 404);
        }

        $subscriberAttribute = $this->attributeRepository
            ->findOneBySubscriberAndAttribute($subscriber, $attributeDefinition);

        if (!$subscriberAttribute) {
            $subscriberAttribute = new SubscriberAttributeValue($attributeDefinition, $subscriber);
        }

        $value = $dto->value ?? $attributeDefinition->getDefaultValue();
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
        $this->definitionRepository->remove($attribute);
    }
}
