<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Exception\SubscriberAttributeCreationException;
use PhpList\Core\Domain\Subscription\Model\Dto\ChangeSetDto;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriberAttributeManager
{
    private SubscriberAttributeValueRepository $attributeRepository;
    private SubscriberAttributeDefinitionRepository $attrDefinitionRepository;
    private EntityManagerInterface $entityManager;
    private TranslatorInterface $translator;

    public function __construct(
        SubscriberAttributeValueRepository $attributeRepository,
        SubscriberAttributeDefinitionRepository $attrDefinitionRepository,
        EntityManagerInterface $entityManager,
        TranslatorInterface $translator,
    ) {
        $this->attributeRepository = $attributeRepository;
        $this->attrDefinitionRepository = $attrDefinitionRepository;
        $this->entityManager = $entityManager;
        $this->translator = $translator;
    }

    public function createOrUpdate(
        Subscriber $subscriber,
        SubscriberAttributeDefinition $definition,
        ?string $value = null
    ): SubscriberAttributeValue {
        // todo: clarify which attributes can be created/updated
        $subscriberAttribute = $this->attributeRepository
            ->findOneBySubscriberAndAttribute($subscriber, $definition);

        if (!$subscriberAttribute) {
            $subscriberAttribute = new SubscriberAttributeValue($definition, $subscriber);
        }

        $value = $value ?? $definition->getDefaultValue();
        if ($value === null) {
            throw new SubscriberAttributeCreationException($this->translator->trans('Value is required'));
        }

        $subscriberAttribute->setValue($value);
        $this->entityManager->persist($subscriberAttribute);

        return $subscriberAttribute;
    }

    public function getSubscriberAttribute(int $subscriberId, int $attributeDefinitionId): ?SubscriberAttributeValue
    {
        return $this->attributeRepository->findOneBySubscriberIdAndAttributeId($subscriberId, $attributeDefinitionId);
    }

    public function delete(SubscriberAttributeValue $attribute): void
    {
        $this->attributeRepository->remove($attribute);
    }

    public function processAttributes(Subscriber $subscriber, array $attributeData): void
    {
        foreach ($attributeData as $key => $value) {
            $lowerKey = strtolower((string)$key);
            if (in_array($lowerKey, ChangeSetDto::IGNORED_ATTRIBUTES, true)) {
                continue;
            }

            $attributeDefinition = $this->attrDefinitionRepository->findOneByName($key);
            if ($attributeDefinition !== null) {
                $this->createOrUpdate(
                    subscriber: $subscriber,
                    definition: $attributeDefinition,
                    value: $value
                );
            }
        }
    }
}
