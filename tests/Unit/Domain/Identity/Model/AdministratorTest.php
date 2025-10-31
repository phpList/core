<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Model;

use DateTime;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\Privileges;
use PhpList\Core\Domain\Identity\Model\PrivilegeFlag;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorTest extends TestCase
{
    use ModelTestTrait;
    use SimilarDatesAssertionTrait;

    private Administrator $subject;

    protected function setUp(): void
    {
        $this->subject = (new Administrator())->setLoginName('');
    }

    public function testIsDomainModel(): void
    {
        self::assertInstanceOf(DomainModel::class, $this->subject);
    }

    public function testGetIdReturnsId(): void
    {
        $id = 123456;
        $this->setSubjectId($this->subject, $id);

        self::assertSame($id, $this->subject->getId());
    }

    public function testGetLoginNameInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getLoginName());
    }

    public function testSetLoginNameSetsLoginName(): void
    {
        $value = 'jane.doe';
        $this->subject->setLoginName($value);

        self::assertSame($value, $this->subject->getLoginName());
    }

    public function testGetEmailAddressInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getEmail());
    }

    public function testSetEmailAddressSetsEmailAddress(): void
    {
        $value = 'oliver@example.com';
        $this->subject->setEmail($value);

        self::assertSame($value, $this->subject->getEmail());
    }

    public function testGetUpdatedAtInitiallyReturnsNotNull(): void
    {
        self::assertNotNull($this->subject->getUpdatedAt());
    }

    public function testUpdateModificationDateSetsModificationDateToNow(): void
    {
        $this->subject->setEmail('update@email.com');

        self::assertSimilarDates(new DateTime(), $this->subject->getUpdatedAt());
    }

    public function testGetPasswordHashInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getPasswordHash());
    }

    public function testSetPasswordHashSetsPasswordHash(): void
    {
        $value = 'Club-Mate';
        $this->subject->setPasswordHash($value);

        self::assertSame($value, $this->subject->getPasswordHash());
    }

    public function testGetPasswordChangeDateInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getPasswordChangeDate());
    }

    public function testSetPasswordHashSetsPasswordChangeDateToNow(): void
    {
        $date = new DateTime();
        $this->subject->setPasswordHash('Zaphod Beeblebrox');

        self::assertSimilarDates($date, $this->subject->getPasswordChangeDate());
    }

    public function testIsDisabledInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->isDisabled());
    }

    public function testSetDisabledSetsDisabled(): void
    {
        $this->subject->setDisabled(true);

        self::assertTrue($this->subject->isDisabled());
    }

    public function testIsSuperUserInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->isSuperUser());
    }

    public function testSetSuperUserSetsSuperUser(): void
    {
        $this->subject->setSuperUser(true);

        self::assertTrue($this->subject->isSuperUser());
    }

    public function testGetPrivilegesInitiallyReturnsEmptyPrivileges(): void
    {
        $privileges = $this->subject->getPrivileges();

        self::assertInstanceOf(Privileges::class, $privileges);

        foreach (PrivilegeFlag::cases() as $flag) {
            self::assertFalse($privileges->has($flag));
        }
    }

    public function testSetPrivilegesSetsPrivileges(): void
    {
        $privileges = Privileges::fromSerialized('');
        $privileges = $privileges->grant(PrivilegeFlag::Subscribers);

        $this->subject->setPrivileges($privileges);

        $retrievedPrivileges = $this->subject->getPrivileges();
        self::assertTrue($retrievedPrivileges->has(PrivilegeFlag::Subscribers));
        self::assertFalse($retrievedPrivileges->has(PrivilegeFlag::Campaigns));
    }

    public function testSetPrivilegesWithMultiplePrivileges(): void
    {
        $privileges = Privileges::fromSerialized('');
        $privileges = $privileges
            ->grant(PrivilegeFlag::Subscribers)
            ->grant(PrivilegeFlag::Campaigns);

        $this->subject->setPrivileges($privileges);

        $retrievedPrivileges = $this->subject->getPrivileges();
        self::assertTrue($retrievedPrivileges->has(PrivilegeFlag::Subscribers));
        self::assertTrue($retrievedPrivileges->has(PrivilegeFlag::Campaigns));
        self::assertFalse($retrievedPrivileges->has(PrivilegeFlag::Statistics));
    }
}
