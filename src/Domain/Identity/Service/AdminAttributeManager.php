<?php

declare(strict_types=1);

namespace PhpList\Core\Domain\Identity\Service;

use PhpList\Core\Domain\Identity\Model\AdminAttributeDefinition;
use PhpList\Core\Domain\Identity\Model\AdminAttributeValue;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Repository\AdminAttributeValueRepository;
use PhpList\Core\Domain\Identity\Exception\AdminAttributeCreationException;

class AdminAttributeManager
{
    private AdminAttributeValueRepository $attributeRepository;

    public function __construct(AdminAttributeValueRepository $attributeRepository)
    {
        $this->attributeRepository = $attributeRepository;
    }

    public function createOrUpdate(
        Administrator $admin,
        AdminAttributeDefinition $definition,
        ?string $value = null
    ): AdminAttributeValue {
        $adminAttribute = $this->attributeRepository->findOneByAdminIdAndAttributeId(
            adminId: $admin->getId(),
            definitionId:  $definition->getId()
        );

        if (!$adminAttribute) {
            $adminAttribute = new AdminAttributeValue(attributeDefinition: $definition, administrator: $admin);
        }

        $value = $value ?? $definition->getDefaultValue();
        if ($value === null) {
            throw new AdminAttributeCreationException('Value is required', 400);
        }

        $adminAttribute->setValue($value);
        $this->attributeRepository->save($adminAttribute);

        return $adminAttribute;
    }

    public function getAdminAttribute(int $adminId, int $attributeDefinitionId): ?AdminAttributeValue
    {
        return $this->attributeRepository->findOneByAdminIdAndAttributeId(
            adminId: $adminId,
            definitionId: $attributeDefinitionId
        );
    }

    public function delete(AdminAttributeValue $attribute): void
    {
        $this->attributeRepository->remove($attribute);
    }
}
