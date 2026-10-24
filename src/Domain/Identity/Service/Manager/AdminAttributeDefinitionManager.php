<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service\Manager;

use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Common\Exception\AttributeDefinitionCreationException;
use PhpList\Core\Domain\Identity\Model\AdminAttributeDefinition;
use PhpList\Core\Domain\Identity\Model\Dto\AdminAttributeDefinitionDto;
use PhpList\Core\Domain\Identity\Repository\AdminAttributeDefinitionRepository;
use PhpList\Core\Domain\Identity\Validator\AttributeTypeValidator;
use Symfony\Contracts\Translation\TranslatorInterface;

class AdminAttributeDefinitionManager
{
    public function __construct(
        private readonly AdminAttributeDefinitionRepository $definitionRepository,
        private readonly AttributeTypeValidator $attributeTypeValidator,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function create(AdminAttributeDefinitionDto $attributeDefinitionDto): AdminAttributeDefinition
    {
        $existingAttribute = $this->definitionRepository->findOneByName($attributeDefinitionDto->name);
        if ($existingAttribute) {
            throw new AttributeDefinitionCreationException(
                $this->translator->trans('Attribute definition already exists.'),
                409
            );
        }
        $this->attributeTypeValidator->validate($attributeDefinitionDto->type);

        $attributeDefinition = (new AdminAttributeDefinition($attributeDefinitionDto->name))
            ->setType($attributeDefinitionDto->type)
            ->setListOrder($attributeDefinitionDto->listOrder)
            ->setRequired($attributeDefinitionDto->required)
            ->setDefaultValue($attributeDefinitionDto->defaultValue);

        $this->definitionRepository->persist($attributeDefinition);

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
        $this->attributeTypeValidator->validate($attributeDefinitionDto->type);

        $attributeDefinition
            ->setName($attributeDefinitionDto->name)
            ->setType($attributeDefinitionDto->type)
            ->setListOrder($attributeDefinitionDto->listOrder)
            ->setRequired($attributeDefinitionDto->required)
            ->setDefaultValue($attributeDefinitionDto->defaultValue);

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

    public function getAttributesAfterId(int $afterId, int $limit): PaginatedResult
    {
        return $this->definitionRepository->getAfterId($afterId, $limit);
    }
}
