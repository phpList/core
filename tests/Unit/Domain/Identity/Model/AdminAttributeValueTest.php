<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Model;

use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Identity\Model\AdminAttributeDefinition;
use PhpList\Core\Domain\Identity\Model\AdminAttributeValue;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PHPUnit\Framework\TestCase;

/**
 * Testcase for the AdminAttributeValue model.
 */
class AdminAttributeValueTest extends TestCase
{
    private AdminAttributeValue $subject;
    private int $adminAttributeId = 1;
    private int $adminId = 2;
    private string $value = 'test-value';
    private AdminAttributeDefinition $attributeDefinition;
    private Administrator $administrator;

    protected function setUp(): void
    {
        $this->attributeDefinition = $this->createMock(AdminAttributeDefinition::class);
        $this->attributeDefinition->method('getId')->willReturn($this->adminAttributeId);

        $this->administrator = $this->createMock(Administrator::class);
        $this->administrator->method('getId')->willReturn($this->adminId);

        $this->subject = new AdminAttributeValue($this->attributeDefinition, $this->administrator, $this->value);
    }

    public function testIsDomainModel(): void
    {
        self::assertInstanceOf(DomainModel::class, $this->subject);
    }

    public function testGetAttributeDefinitionReturnsAttributeDefinition(): void
    {
        self::assertSame($this->attributeDefinition, $this->subject->getAttributeDefinition());
    }

    public function testGetAdministratorReturnsAdministrator(): void
    {
        self::assertSame($this->administrator, $this->subject->getAdministrator());
    }

    public function testGetValueReturnsValue(): void
    {
        self::assertSame($this->value, $this->subject->getValue());
    }

    public function testSetValueSetsValue(): void
    {
        $newValue = 'new-value';
        $this->subject->setValue($newValue);

        self::assertSame($newValue, $this->subject->getValue());
    }

    public function testSetValueReturnsInstance(): void
    {
        $result = $this->subject->setValue('new-value');

        self::assertSame($this->subject, $result);
    }

    public function testSetValueCanSetNull(): void
    {
        $this->subject->setValue(null);

        self::assertNull($this->subject->getValue());
    }
}
