<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Domain\Model\Identity;

use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Tests\Unit\Domain\Model\Traits\ModelTestTrait;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorTest extends TestCase
{
    use ModelTestTrait;

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
    public function getNormalizedLoginNameInitiallyReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getNormalizedLoginName());
    }

    /**
     * @test
     */
    public function setNormalizedLoginNameSetsNormalizedLoginName()
    {
        $value = 'jane-doe';
        $this->subject->setNormalizedLoginName($value);

        self::assertSame($value, $this->subject->getNormalizedLoginName());
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
    public function setCreationDateSetsCreationDate()
    {
        $date = new \DateTime();
        $this->subject->setCreationDate($date);

        self::assertSame($date, $this->subject->getCreationDate());
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
    public function setModificationDateSetsModificationDate()
    {
        $date = new \DateTime();
        $this->subject->setModificationDate($date);

        self::assertSame($date, $this->subject->getModificationDate());
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
