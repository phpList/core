<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Domain\Model\Identity;

use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Tests\Support\Traits\ModelTestTrait;
use PhpList\PhpList4\Tests\Support\Traits\SimilarDatesAssertionTrait;
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
    public function getIdInitiallyReturnsZero()
    {
        self::assertSame(0, $this->subject->getId());
    }

    /**
     * @test
     */
    public function getIdReturnsId()
    {
        $id = 123456;
        $this->setSubjectId($id);

        self::assertSame($id, $this->subject->getId());
    }

    /**
     * @test
     */
    public function getLoginNameInitiallyReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getLoginName());
    }

    /**
     * @test
     */
    public function setLoginNameSetsLoginName()
    {
        $value = 'jane.doe';
        $this->subject->setLoginName($value);

        self::assertSame($value, $this->subject->getLoginName());
    }

    /**
     * @test
     */
    public function getEmailAddressInitiallyReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getEmailAddress());
    }

    /**
     * @test
     */
    public function setEmailAddressSetsEmailAddress()
    {
        $value = 'oliver@example.com';
        $this->subject->setEmailAddress($value);

        self::assertSame($value, $this->subject->getEmailAddress());
    }

    /**
     * @test
     */
    public function getCreationDateInitiallyReturnsNull()
    {
        self::assertNull($this->subject->getCreationDate());
    }

    /**
     * @test
     */
    public function updateCreationDateSetsCreationDateToNow()
    {
        $this->subject->updateCreationDate();

        self::assertSimilarDates(new \DateTime(), $this->subject->getCreationDate());
    }

    /**
     * @test
     */
    public function getModificationDateInitiallyReturnsNull()
    {
        self::assertNull($this->subject->getModificationDate());
    }

    /**
     * @test
     */
    public function updateModificationDateSetsModificationDateToNow()
    {
        $this->subject->updateModificationDate();

        self::assertSimilarDates(new \DateTime(), $this->subject->getModificationDate());
    }

    /**
     * @test
     */
    public function getPasswordHashInitiallyReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getPasswordHash());
    }

    /**
     * @test
     */
    public function setPasswordHashSetsPasswordHash()
    {
        $value = 'Club-Mate';
        $this->subject->setPasswordHash($value);

        self::assertSame($value, $this->subject->getPasswordHash());
    }

    /**
     * @test
     */
    public function getPasswordChangeDateInitiallyReturnsNull()
    {
        self::assertNull($this->subject->getPasswordChangeDate());
    }

    /**
     * @test
     */
    public function setPasswordHashSetsPasswordChangeDateToNow()
    {
        $date = new \DateTime();
        $this->subject->setPasswordHash('Zaphod Beeblebrox');

        self::assertSimilarDates($date, $this->subject->getPasswordChangeDate());
    }

    /**
     * @test
     */
    public function isDisabledInitiallyReturnsFalse()
    {
        self::assertFalse($this->subject->isDisabled());
    }

    /**
     * @test
     */
    public function setDisabledSetsDisabled()
    {
        $this->subject->setDisabled(true);

        self::assertTrue($this->subject->isDisabled());
    }
}
