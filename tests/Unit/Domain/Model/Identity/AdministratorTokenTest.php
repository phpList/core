<?php
declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Model\Identity;

use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Identity\AdministratorToken;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Tests\TestingSupport\Traits\ModelTestTrait;
use PhpList\Core\Tests\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AdministratorTokenTest extends TestCase
{
    use ModelTestTrait;
    use SimilarDatesAssertionTrait;

    /**
     * @var AdministratorToken
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new AdministratorToken();
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
    public function getKeyInitiallyReturnsEmptyString()
    {
        static::assertSame('', $this->subject->getKey());
    }

    /**
     * @test
     */
    public function setKeySetsKey()
    {
        $value = 'Club-Mate';
        $this->subject->setKey($value);

        static::assertSame($value, $this->subject->getKey());
    }

    /**
     * @test
     */
    public function getExpiryInitiallyReturnsDateTime()
    {
        static::assertInstanceOf(\DateTime::class, $this->subject->getExpiry());
    }

    /**
     * @test
     */
    public function generateExpirySetsExpiryOneHourInTheFuture()
    {
        $this->subject->generateExpiry();

        static::assertSimilarDates(new \DateTime('+1 hour'), $this->subject->getExpiry());
    }

    /**
     * @test
     */
    public function generateKeyCreates32CharacterKey()
    {
        $this->subject->generateKey();

        static::assertRegExp('/^[a-z0-9]{32}$/', $this->subject->getKey());
    }

    /**
     * @test
     */
    public function generateKeyCreatesDifferentKeysForEachCall()
    {
        $this->subject->generateKey();
        $firstKey = $this->subject->getKey();

        $this->subject->generateKey();
        $secondKey = $this->subject->getKey();

        static::assertNotSame($firstKey, $secondKey);
    }

    /**
     * @test
     */
    public function getAdministratorInitiallyReturnsNull()
    {
        static::assertNull($this->subject->getAdministrator());
    }

    /**
     * @test
     */
    public function setAdministratorSetsAdministrator()
    {
        $model = new Administrator();
        $this->subject->setAdministrator($model);

        static::assertSame($model, $this->subject->getAdministrator());
    }
}
