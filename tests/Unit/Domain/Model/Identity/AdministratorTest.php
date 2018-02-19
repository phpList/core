<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Domain\Model\Identity;

use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Model\Interfaces\DomainModel;
use PhpList\PhpList4\TestingSupport\Traits\ModelTestTrait;
use PhpList\PhpList4\TestingSupport\Traits\SimilarDatesAssertionTrait;
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

    /**
     * @var Administrator
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new Administrator();
    }

    /**
     * @test
     */
    public function isDomainModel()
    {
        static::assertInstanceOf(DomainModel::class, $this->subject);
    }

    /**
     * @test
     */
    public function getIdInitiallyReturnsZero()
    {
        static::assertSame(0, $this->subject->getId());
    }

    /**
     * @test
     */
    public function getIdReturnsId()
    {
        $id = 123456;
        $this->setSubjectId($id);

        static::assertSame($id, $this->subject->getId());
    }

    /**
     * @test
     */
    public function getLoginNameInitiallyReturnsEmptyString()
    {
        static::assertSame('', $this->subject->getLoginName());
    }

    /**
     * @test
     */
    public function setLoginNameSetsLoginName()
    {
        $value = 'jane.doe';
        $this->subject->setLoginName($value);

        static::assertSame($value, $this->subject->getLoginName());
    }

    /**
     * @test
     */
    public function getEmailAddressInitiallyReturnsEmptyString()
    {
        static::assertSame('', $this->subject->getEmailAddress());
    }

    /**
     * @test
     */
    public function setEmailAddressSetsEmailAddress()
    {
        $value = 'oliver@example.com';
        $this->subject->setEmailAddress($value);

        static::assertSame($value, $this->subject->getEmailAddress());
    }

    /**
     * @test
     */
    public function getCreationDateInitiallyReturnsNull()
    {
        static::assertNull($this->subject->getCreationDate());
    }

    /**
     * @test
     */
    public function updateCreationDateSetsCreationDateToNow()
    {
        $this->subject->updateCreationDate();

        static::assertSimilarDates(new \DateTime(), $this->subject->getCreationDate());
    }

    /**
     * @test
     */
    public function getModificationDateInitiallyReturnsNull()
    {
        static::assertNull($this->subject->getModificationDate());
    }

    /**
     * @test
     */
    public function updateModificationDateSetsModificationDateToNow()
    {
        $this->subject->updateModificationDate();

        static::assertSimilarDates(new \DateTime(), $this->subject->getModificationDate());
    }

    /**
     * @test
     */
    public function getPasswordHashInitiallyReturnsEmptyString()
    {
        static::assertSame('', $this->subject->getPasswordHash());
    }

    /**
     * @test
     */
    public function setPasswordHashSetsPasswordHash()
    {
        $value = 'Club-Mate';
        $this->subject->setPasswordHash($value);

        static::assertSame($value, $this->subject->getPasswordHash());
    }

    /**
     * @test
     */
    public function getPasswordChangeDateInitiallyReturnsNull()
    {
        static::assertNull($this->subject->getPasswordChangeDate());
    }

    /**
     * @test
     */
    public function setPasswordHashSetsPasswordChangeDateToNow()
    {
        $date = new \DateTime();
        $this->subject->setPasswordHash('Zaphod Beeblebrox');

        static::assertSimilarDates($date, $this->subject->getPasswordChangeDate());
    }

    /**
     * @test
     */
    public function isDisabledInitiallyReturnsFalse()
    {
        static::assertFalse($this->subject->isDisabled());
    }

    /**
     * @test
     */
    public function setDisabledSetsDisabled()
    {
        $this->subject->setDisabled(true);

        static::assertTrue($this->subject->isDisabled());
    }

    /**
     * @test
     */
    public function isSuperUserInitiallyReturnsFalse()
    {
        static::assertFalse($this->subject->isSuperUser());
    }

    /**
     * @test
     */
    public function setSuperUserSetsSuperUser()
    {
        $this->subject->setSuperUser(true);

        static::assertTrue($this->subject->isSuperUser());
    }
}
