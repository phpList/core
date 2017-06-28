<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Domain\Model\Identity;

use PhpList\PhpList4\Domain\Model\Identity\AdministratorToken;
use PhpList\PhpList4\Tests\Support\Traits\ModelTestTrait;
use PhpList\PhpList4\Tests\Support\Traits\SimilarDatesAssertionTrait;
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
    public function getKeyInitiallyReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getKey());
    }

    /**
     * @test
     */
    public function setKeySetsKey()
    {
        $value = 'Club-Mate';
        $this->subject->setKey($value);

        self::assertSame($value, $this->subject->getKey());
    }

    /**
     * @test
     */
    public function getExpiryInitiallyReturnsDateTime()
    {
        self::assertInstanceOf(\DateTime::class, $this->subject->getExpiry());
    }

    /**
     * @test
     */
    public function setExpirySetsExpiry()
    {
        $expiry = new \DateTime();
        $this->subject->setExpiry($expiry);

        self::assertSame($expiry, $this->subject->getExpiry());
    }

    /**
     * @test
     */
    public function generateExpirySetsExpiryOneHourInTheFuture()
    {
        $this->subject->generateExpiry();

        self::assertSimilarDates(new \DateTime('+1 hour'), $this->subject->getExpiry());
    }

    /**
     * @test
     */
    public function generateKeyCreates32CharacterKey()
    {
        $this->subject->generateKey();

        self::assertRegExp('/^[a-z0-9]{32}$/', $this->subject->getKey());
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

        self::assertNotSame($firstKey, $secondKey);
    }
}
