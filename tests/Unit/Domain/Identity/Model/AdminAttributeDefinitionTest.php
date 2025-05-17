<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Model;

use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Common\Model\Interfaces\Identity;
use PhpList\Core\Domain\Identity\Model\AdminAttributeDefinition;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Testcase for the AdminAttributeDefinition model.
 */
class AdminAttributeDefinitionTest extends TestCase
{
    use ModelTestTrait;

    private AdminAttributeDefinition $subject;
    private string $name = 'test-attribute';
    private string $type = 'text';
    private int $listOrder = 10;
    private string $defaultValue = 'default';
    private bool $required = true;
    private string $tableName = 'test_table';

    protected function setUp(): void
    {
        $this->subject = new AdminAttributeDefinition(
            $this->name,
            $this->type,
            $this->listOrder,
            $this->defaultValue,
            $this->required,
            $this->tableName
        );
    }

    public function testIsDomainModel(): void
    {
        self::assertInstanceOf(DomainModel::class, $this->subject);
    }

    public function testGetIdInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getId());
    }

    public function testGetIdReturnsId(): void
    {
        $id = 123456;
        $this->setSubjectId($this->subject, $id);

        self::assertSame($id, $this->subject->getId());
    }

    public function testGetNameReturnsName(): void
    {
        self::assertSame($this->name, $this->subject->getName());
    }

    public function testSetNameSetsName(): void
    {
        $newName = 'new-name';
        $this->subject->setName($newName);

        self::assertSame($newName, $this->subject->getName());
    }

    public function testSetNameReturnsInstance(): void
    {
        $result = $this->subject->setName('new-name');

        self::assertSame($this->subject, $result);
    }

    public function testGetTypeReturnsType(): void
    {
        self::assertSame($this->type, $this->subject->getType());
    }

    public function testSetTypeSetsType(): void
    {
        $newType = 'checkbox';
        $this->subject->setType($newType);

        self::assertSame($newType, $this->subject->getType());
    }

    public function testSetTypeReturnsInstance(): void
    {
        $result = $this->subject->setType('checkbox');

        self::assertSame($this->subject, $result);
    }

    public function testSetTypeCanSetNull(): void
    {
        $this->subject->setType(null);

        self::assertNull($this->subject->getType());
    }

    public function testGetListOrderReturnsListOrder(): void
    {
        self::assertSame($this->listOrder, $this->subject->getListOrder());
    }

    public function testSetListOrderSetsListOrder(): void
    {
        $newListOrder = 20;
        $this->subject->setListOrder($newListOrder);

        self::assertSame($newListOrder, $this->subject->getListOrder());
    }

    public function testSetListOrderReturnsInstance(): void
    {
        $result = $this->subject->setListOrder(20);

        self::assertSame($this->subject, $result);
    }

    public function testSetListOrderCanSetNull(): void
    {
        $this->subject->setListOrder(null);

        self::assertNull($this->subject->getListOrder());
    }

    public function testGetDefaultValueReturnsDefaultValue(): void
    {
        self::assertSame($this->defaultValue, $this->subject->getDefaultValue());
    }

    public function testSetDefaultValueSetsDefaultValue(): void
    {
        $newDefaultValue = 'new-default';
        $this->subject->setDefaultValue($newDefaultValue);

        self::assertSame($newDefaultValue, $this->subject->getDefaultValue());
    }

    public function testSetDefaultValueReturnsInstance(): void
    {
        $result = $this->subject->setDefaultValue('new-default');

        self::assertSame($this->subject, $result);
    }

    public function testSetDefaultValueCanSetNull(): void
    {
        $this->subject->setDefaultValue(null);

        self::assertNull($this->subject->getDefaultValue());
    }

    public function testIsRequiredReturnsRequired(): void
    {
        self::assertSame($this->required, $this->subject->isRequired());
    }

    public function testSetRequiredSetsRequired(): void
    {
        $this->subject->setRequired(false);

        self::assertSame(false, $this->subject->isRequired());
    }

    public function testSetRequiredReturnsInstance(): void
    {
        $result = $this->subject->setRequired(false);

        self::assertSame($this->subject, $result);
    }

    public function testSetRequiredCanSetNull(): void
    {
        $this->subject->setRequired(null);

        self::assertNull($this->subject->isRequired());
    }

    public function testGetTableNameReturnsTableName(): void
    {
        self::assertSame($this->tableName, $this->subject->getTableName());
    }

    public function testSetTableNameSetsTableName(): void
    {
        $newTableName = 'new_table';
        $this->subject->setTableName($newTableName);

        self::assertSame($newTableName, $this->subject->getTableName());
    }

    public function testSetTableNameReturnsInstance(): void
    {
        $result = $this->subject->setTableName('new_table');

        self::assertSame($this->subject, $result);
    }

    public function testSetTableNameCanSetNull(): void
    {
        $this->subject->setTableName(null);

        self::assertNull($this->subject->getTableName());
    }
}
