<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Model\Identity;

use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
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
        $this->subject = new Administrator();
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
        self::assertSame('', $this->subject->getEmailAddress());
    }

    public function testSetEmailAddressSetsEmailAddress(): void
    {
        $value = 'oliver@example.com';
        $this->subject->setEmailAddress($value);

        self::assertSame($value, $this->subject->getEmailAddress());
    }

    public function testGetCreationDateInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getCreationDate());
    }

    public function testUpdateCreationDateSetsCreationDateToNow(): void
    {
        $this->subject->updateCreationDate();

        self::assertSimilarDates(new \DateTime(), $this->subject->getCreationDate());
    }

    public function testGetModificationDateInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getModificationDate());
    }

    public function testUpdateModificationDateSetsModificationDateToNow(): void
    {
        $this->subject->updateModificationDate();

        self::assertSimilarDates(new \DateTime(), $this->subject->getModificationDate());
    }

    public function testGetPasswordHashInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getPasswordHash());
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
        $date = new \DateTime();
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
}
