<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Service\Manager;

use PhpList\Core\Domain\Exception\AttributeDefinitionCreationException;
use PhpList\Core\Domain\Model\Subscription\AttributeDefinition;
use PhpList\Core\Domain\Model\Subscription\Dto\AttributeDefinitionDto;
use PhpList\Core\Domain\Repository\Subscription\AttributeDefinitionRepository;

class AttributeDefinitionManager
{
    private AttributeDefinitionRepository $definitionRepository;

    public function __construct(AttributeDefinitionRepository $definitionRepository)
    {
        $this->definitionRepository = $definitionRepository;
    }

    public function create(AttributeDefinitionDto $attributeDefinitionDto): AttributeDefinition
    {
        $existingAttribute = $this->definitionRepository->findOneByName($attributeDefinitionDto->name);
        if ($existingAttribute) {
            throw new AttributeDefinitionCreationException('Attribute definition already exists', 409);
        }

        $attributeDefinition = (new AttributeDefinition())
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
        AttributeDefinition $attributeDefinition,
        AttributeDefinitionDto $attributeDefinitionDto
    ): AttributeDefinition {
        $existingAttribute = $this->definitionRepository->findOneByName($attributeDefinitionDto->name);
        if ($existingAttribute && $existingAttribute->getId() !== $attributeDefinition->getId()) {
            throw new AttributeDefinitionCreationException('Another attribute with this name already exists.', 409);
        }

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

    public function delete(AttributeDefinition $attributeDefinition): void
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
