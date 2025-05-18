<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service;

use PhpList\Core\Domain\Identity\Model\AdminAttributeDefinition;
use PhpList\Core\Domain\Identity\Model\Dto\AdminAttributeDefinitionDto;
use PhpList\Core\Domain\Identity\Repository\AdminAttributeDefinitionRepository;
use PhpList\Core\Domain\Identity\Exception\AttributeDefinitionCreationException;

class AdminAttributeDefinitionManager
{
    private AdminAttributeDefinitionRepository $definitionRepository;

    public function __construct(AdminAttributeDefinitionRepository $definitionRepository)
    {
        $this->definitionRepository = $definitionRepository;
    }

    public function create(AdminAttributeDefinitionDto $attributeDefinitionDto): AdminAttributeDefinition
    {
        $existingAttribute = $this->definitionRepository->findOneByName($attributeDefinitionDto->name);
        if ($existingAttribute) {
            throw new AttributeDefinitionCreationException('Attribute definition already exists', 409);
        }

        $attributeDefinition = (new AdminAttributeDefinition($attributeDefinitionDto->name))
            ->setType($attributeDefinitionDto->type)
            ->setListOrder($attributeDefinitionDto->listOrder)
            ->setRequired($attributeDefinitionDto->required)
            ->setDefaultValue($attributeDefinitionDto->defaultValue)
            ->setTableName($attributeDefinitionDto->tableName);

        $this->definitionRepository->save($attributeDefinition);

        return $attributeDefinition;
    }

    public function update(
        AdminAttributeDefinition $attributeDefinition,
        AdminAttributeDefinitionDto $attributeDefinitionDto
    ): AdminAttributeDefinition {
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

    public function delete(AdminAttributeDefinition $attributeDefinition): void
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
