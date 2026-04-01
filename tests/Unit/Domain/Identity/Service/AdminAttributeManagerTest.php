<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service;

use PhpList\Core\Domain\Identity\Exception\AdminAttributeCreationException;
use PhpList\Core\Domain\Identity\Model\AdminAttributeDefinition;
use PhpList\Core\Domain\Identity\Model\AdminAttributeValue;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Repository\AdminAttributeValueRepository;
use PhpList\Core\Domain\Identity\Service\Manager\AdminAttributeManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AdminAttributeManagerTest extends TestCase
{
    private AdminAttributeValueRepository&MockObject $repository;
    private AdminAttributeManager $subject;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AdminAttributeValueRepository::class);
        $this->subject = new AdminAttributeManager($this->repository);
    }

    public function testCreateOrUpdateCreatesNewAttributeIfNotExists(): void
    {
        $admin = $this->createMock(Administrator::class);
        $admin->method('getId')->willReturn(1);

        $definition = $this->createMock(AdminAttributeDefinition::class);
        $definition->method('getId')->willReturn(2);
        $definition->method('getDefaultValue')->willReturn(null);

        $value = 'test-value';

        $this->repository->expects($this->once())
            ->method('findOneByAdminIdAndAttributeId')
            ->with(1, 2)
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (AdminAttributeValue $attribute) use ($value) {
                return $attribute->getAdministrator()->getId() === 1
                    && $attribute->getAttributeDefinition()->getId() === 2
                    && $attribute->getValue() === $value;
            }));

        $result = $this->subject->createOrUpdate($admin, $definition, $value);

        $this->assertInstanceOf(AdminAttributeValue::class, $result);
        $this->assertEquals(1, $result->getAdministrator()->getId());
        $this->assertEquals(2, $result->getAttributeDefinition()->getId());
        $this->assertEquals($value, $result->getValue());
    }

    public function testCreateOrUpdateUpdatesExistingAttribute(): void
    {
        $admin = $this->createMock(Administrator::class);
        $admin->method('getId')->willReturn(1);

        $definition = $this->createMock(AdminAttributeDefinition::class);
        $definition->method('getId')->willReturn(2);

        $existingAttribute = new AdminAttributeValue($definition, $admin, 'old-value');
        $newValue = 'new-value';

        $this->repository->expects($this->once())
            ->method('findOneByAdminIdAndAttributeId')
            ->with(1, 2)
            ->willReturn($existingAttribute);

        $this->repository->expects($this->never())
            ->method('save')
            ->with($this->callback(function (AdminAttributeValue $attribute) use ($newValue) {
                return $attribute->getValue() === $newValue;
            }));

        $result = $this->subject->createOrUpdate($admin, $definition, $newValue);

        $this->assertSame($existingAttribute, $result);
        $this->assertEquals($newValue, $result->getValue());
    }

    public function testCreateOrUpdateUsesDefaultValueIfValueIsNull(): void
    {
        $admin = $this->createMock(Administrator::class);
        $admin->method('getId')->willReturn(1);

        $defaultValue = 'default-value';
        $definition = $this->createMock(AdminAttributeDefinition::class);
        $definition->method('getId')->willReturn(2);
        $definition->method('getDefaultValue')->willReturn($defaultValue);

        $this->repository->expects($this->once())
            ->method('findOneByAdminIdAndAttributeId')
            ->willReturn(null);

        // will not throw AdminAttributeCreationException

        $result = $this->subject->createOrUpdate($admin, $definition);

        $this->assertEquals($defaultValue, $result->getValue());
    }

    public function testCreateOrUpdateThrowsExceptionIfValueAndDefaultValueAreNull(): void
    {
        $admin = $this->createMock(Administrator::class);
        $admin->method('getId')->willReturn(1);
        $definition = $this->createMock(AdminAttributeDefinition::class);
        $definition->method('getId')->willReturn(2);
        $definition->method('getDefaultValue')->willReturn(null);

        $this->repository->expects($this->once())
            ->method('findOneByAdminIdAndAttributeId')
            ->willReturn(null);

        $this->expectException(AdminAttributeCreationException::class);
        $this->expectExceptionMessage('Value is required');

        $this->subject->createOrUpdate($admin, $definition);
    }

    public function testGetAdminAttributeReturnsAttributeFromRepository(): void
    {
        $adminId = 1;
        $attributeId = 2;

        $admin = $this->createMock(Administrator::class);
        $admin->method('getId')->willReturn($adminId);

        $definition = $this->createMock(AdminAttributeDefinition::class);
        $definition->method('getId')->willReturn($attributeId);

        $attribute = new AdminAttributeValue($definition, $admin, 'value');

        $this->repository->expects($this->once())
            ->method('findOneByAdminIdAndAttributeId')
            ->with($adminId, $attributeId)
            ->willReturn($attribute);

        $result = $this->subject->getAdminAttribute($adminId, $attributeId);

        $this->assertSame($attribute, $result);
    }

    public function testGetAdminAttributeReturnsNullIfNotFound(): void
    {
        $this->repository->expects($this->once())
            ->method('findOneByAdminIdAndAttributeId')
            ->willReturn(null);

        $result = $this->subject->getAdminAttribute(1, 2);

        $this->assertNull($result);
    }

    public function testDeleteCallsRemoveOnRepository(): void
    {
        $attribute = $this->createMock(AdminAttributeValue::class);

        $this->repository->expects($this->once())
            ->method('remove')
            ->with($attribute);

        $this->subject->delete($attribute);
    }
}
