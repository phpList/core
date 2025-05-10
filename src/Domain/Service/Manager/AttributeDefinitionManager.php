<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Service\Manager;

use PhpList\Core\Domain\Exception\AttributeDefinitionCreationException;
use PhpList\Core\Domain\Model\Subscription\AttributeDefinition;
use PhpList\Core\Domain\Model\Subscription\Dto\AttributeDefinitionDto;
use PhpList\Core\Domain\Repository\Subscription\AttributeDefinitionRepository;

class AttributeDefinitionManager
{
    private AttributeDefinitionRepository $attributeDefinitionRepository;

    public function __construct(AttributeDefinitionRepository $attributeDefinitionRepository)
    {
        $this->attributeDefinitionRepository = $attributeDefinitionRepository;
    }

    public function create(AttributeDefinitionDto $attributeDefinitionDto): AttributeDefinition
    {
        $existingAttribute = $this->attributeDefinitionRepository->findOneByName($attributeDefinitionDto->name);
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

        $this->attributeDefinitionRepository->save($attributeDefinition);

        return $attributeDefinition;
    }

    public function update(
        AttributeDefinition $attributeDefinition,
        AttributeDefinitionDto $attributeDefinitionDto
    ): AttributeDefinition {
        $existingAttribute = $this->attributeDefinitionRepository->findOneByName($attributeDefinitionDto->name);
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

        $this->attributeDefinitionRepository->save($attributeDefinition);

        return $attributeDefinition;
    }

    public function delete(AttributeDefinition $attributeDefinition): void
    {
        $this->attributeDefinitionRepository->remove($attributeDefinition);
    }

    public function getTotalCount(): int
    {
        return $this->attributeDefinitionRepository->count();
    }

    public function getAttributesAfterId(int $afterId, int $limit): array
    {
        return $this->attributeDefinitionRepository->getAfterId($afterId, $limit);
    }
}
