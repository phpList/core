<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Subscription\Service\Manager;

use PhpList\Core\Domain\Subscription\Exception\AttributeDefinitionCreationException;
use PhpList\Core\Domain\Subscription\Model\Dto\AttributeDefinitionDto;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeDefinition;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeDefinitionRepository;
use PhpList\Core\Domain\Subscription\Validator\AttributeTypeValidator;

class AttributeDefinitionManager
{
    private SubscriberAttributeDefinitionRepository $definitionRepository;
    private AttributeTypeValidator $attributeTypeValidator;

    public function __construct(
        SubscriberAttributeDefinitionRepository $definitionRepository,
        AttributeTypeValidator $attributeTypeValidator
    ) {
        $this->definitionRepository = $definitionRepository;
        $this->attributeTypeValidator = $attributeTypeValidator;
    }

    public function create(AttributeDefinitionDto $attributeDefinitionDto): SubscriberAttributeDefinition
    {
        $existingAttribute = $this->definitionRepository->findOneByName($attributeDefinitionDto->name);
        if ($existingAttribute) {
            throw new AttributeDefinitionCreationException('Attribute definition already exists', 409);
        }
        $this->attributeTypeValidator->validate($attributeDefinitionDto->type);

        $attributeDefinition = (new SubscriberAttributeDefinition())
            ->setName($attributeDefinitionDto->name)
            ->setType($attributeDefinitionDto->type)
            ->setListOrder($attributeDefinitionDto->listOrder)
            ->setRequired($attributeDefinitionDto->required)
            ->setDefaultValue($attributeDefinitionDto->defaultValue)
            ->setTableName($attributeDefinitionDto->tableName);

        $this->definitionRepository->save($attributeDefinition);

        return $attributeDefinition;
    }

    public function update(
        SubscriberAttributeDefinition $attributeDefinition,
        AttributeDefinitionDto $attributeDefinitionDto
    ): SubscriberAttributeDefinition {
        $existingAttribute = $this->definitionRepository->findOneByName($attributeDefinitionDto->name);
        if ($existingAttribute && $existingAttribute->getId() !== $attributeDefinition->getId()) {
            throw new AttributeDefinitionCreationException('Another attribute with this name already exists.', 409);
        }
        $this->attributeTypeValidator->validate($attributeDefinitionDto->type);

        $attributeDefinition
            ->setName($attributeDefinitionDto->name)
            ->setType($attributeDefinitionDto->type)
            ->setListOrder($attributeDefinitionDto->listOrder)
            ->setRequired($attributeDefinitionDto->required)
            ->setDefaultValue($attributeDefinitionDto->defaultValue)
            ->setTableName($attributeDefinitionDto->tableName);

        $this->definitionRepository->save($attributeDefinition);

        return $attributeDefinition;
    }

    public function delete(SubscriberAttributeDefinition $attributeDefinition): void
    {
        $this->definitionRepository->remove($attributeDefinition);
    }

    public function getTotalCount(): int
    {
        return $this->definitionRepository->count();
    }

    public function getAttributesAfterId(int $afterId, int $limit): array
    {
        return $this->definitionRepository->getAfterId($afterId, $limit);
    }
}
