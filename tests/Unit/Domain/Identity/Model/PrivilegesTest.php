<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Model;

use InvalidArgumentException;
use PhpList\Core\Domain\Identity\Model\Privileges;
use PhpList\Core\Domain\Identity\Model\PrivilegeFlag;
use PHPUnit\Framework\TestCase;

/**
 * Testcase for the Privileges class.
 */
class PrivilegesTest extends TestCase
{
    private Privileges $subject;

    protected function setUp(): void
    {
        $this->subject = Privileges::fromSerialized('');
    }

    public function testFromSerializedWithInvalidDataThrowsError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid serialized privileges string.');

        Privileges::fromSerialized('invalid data');
    }

    public function testFromSerializedWithValidDataReturnsPopulatedPrivileges(): void
    {
        $data = [PrivilegeFlag::Subscribers->value => true];
        $serialized = serialize($data);
        
        $privileges = Privileges::fromSerialized($serialized);
        
        self::assertTrue($privileges->has(PrivilegeFlag::Subscribers));
    }

    public function testToSerializedReturnsSerializedData(): void
    {
        $privileges = Privileges::fromSerialized('');
        $privileges = $privileges->grant(PrivilegeFlag::Subscribers);
        
        $serialized = $privileges->toSerialized();
        $data = unserialize($serialized);
        
        self::assertTrue($data[PrivilegeFlag::Subscribers->value]);
    }

    public function testHasReturnsFalseForUnsetPrivilege(): void
    {
        self::assertFalse($this->subject->has(PrivilegeFlag::Subscribers));
    }

    public function testHasReturnsTrueForSetPrivilege(): void
    {
        $this->subject = $this->subject->grant(PrivilegeFlag::Subscribers);
        
        self::assertTrue($this->subject->has(PrivilegeFlag::Subscribers));
    }

    public function testGrantSetsPrivilege(): void
    {
        $result = $this->subject->grant(PrivilegeFlag::Subscribers);
        
        self::assertTrue($result->has(PrivilegeFlag::Subscribers));
    }

    public function testGrantReturnsNewInstance(): void
    {
        $result = $this->subject->grant(PrivilegeFlag::Subscribers);
        
        self::assertNotSame($this->subject, $result);
    }

    public function testRevokeClearsPrivilege(): void
    {
        $this->subject = $this->subject->grant(PrivilegeFlag::Subscribers);
        $result = $this->subject->revoke(PrivilegeFlag::Subscribers);
        
        self::assertFalse($result->has(PrivilegeFlag::Subscribers));
    }

    public function testRevokeReturnsNewInstance(): void
    {
        $result = $this->subject->revoke(PrivilegeFlag::Subscribers);
        
        self::assertNotSame($this->subject, $result);
    }

    public function testAllReturnsAllPrivileges(): void
    {
        $this->subject = $this->subject->grant(PrivilegeFlag::Subscribers);
        $all = $this->subject->all();
        
        self::assertTrue($all[PrivilegeFlag::Subscribers->value]);
        self::assertFalse($all[PrivilegeFlag::Campaigns->value]);
    }
}
