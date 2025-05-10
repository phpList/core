<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Service\Manager;

use PhpList\Core\Domain\Exception\SubscriberAttributeCreationException;
use PhpList\Core\Domain\Model\Subscription\Dto\SubscriberAttributeDto;
use PhpList\Core\Domain\Model\Subscription\SubscriberAttribute;
use PhpList\Core\Domain\Repository\Subscription\AttributeDefinitionRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriberAttributeRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriberRepository;

class SubscriberAttributeManager
{
    private AttributeDefinitionRepository $definitionRepository;
    private SubscriberAttributeRepository $attributeRepository;
    private SubscriberRepository $subscriberRepository;

    public function __construct(
        AttributeDefinitionRepository $definitionRepository,
        SubscriberAttributeRepository $attributeRepository,
        SubscriberRepository $subscriberRepository,
    ) {
        $this->definitionRepository = $definitionRepository;
        $this->attributeRepository = $attributeRepository;
        $this->subscriberRepository = $subscriberRepository;
    }

    public function createOrUpdate(SubscriberAttributeDto $dto): SubscriberAttribute
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
            $subscriberAttribute = new SubscriberAttribute($attributeDefinition, $subscriber);
        }

        $subscriberAttribute->setValue($dto->value);
        $this->attributeRepository->save($subscriberAttribute);

        return $subscriberAttribute;
    }

    public function getSubscriberAttribute(int $subscriberId, int $attributeDefinitionId): SubscriberAttribute
    {
        return $this->attributeRepository->findOneBySubscriberIdAndAttributeId($subscriberId, $attributeDefinitionId);
    }

    public function delete(SubscriberAttribute $attribute): void
    {
        $this->definitionRepository->remove($attribute);
    }
}
