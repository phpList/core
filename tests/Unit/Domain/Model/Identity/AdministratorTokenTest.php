<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Model\Identity;

use DateTime;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Identity\AdministratorToken;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
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

    private AdministratorToken $subject;

    protected function setUp(): void
    {
        $this->subject = new AdministratorToken();
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

    public function testGetCreationDateInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getCreationDate());
    }

    public function testUpdateCreationDateSetsCreationDateToNow(): void
    {
        $this->subject->updateCreationDate();

        self::assertSimilarDates(new DateTime(), $this->subject->getCreationDate());
    }

    public function testGetKeyInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getKey());
    }

    public function testSetKeySetsKey(): void
    {
        $value = 'Club-Mate';
        $this->subject->setKey($value);

        self::assertSame($value, $this->subject->getKey());
    }

    public function testGetExpiryInitiallyReturnsDateTime(): void
    {
        self::assertInstanceOf(DateTime::class, $this->subject->getExpiry());
    }

    public function testGenerateExpirySetsExpiryOneHourInTheFuture(): void
    {
        $this->subject->generateExpiry();

        self::assertSimilarDates(new DateTime('+1 hour'), $this->subject->getExpiry());
    }

    public function testGenerateKeyCreates32CharacterKey(): void
    {
        $this->subject->generateKey();

        self::assertMatchesRegularExpression('/^[a-z0-9]{32}$/', $this->subject->getKey());
    }

    public function testGenerateKeyCreatesDifferentKeysForEachCall(): void
    {
        $this->subject->generateKey();
        $firstKey = $this->subject->getKey();

        $this->subject->generateKey();
        $secondKey = $this->subject->getKey();

        self::assertNotSame($firstKey, $secondKey);
    }

    public function testGetAdministratorInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getAdministrator());
    }

    public function testSetAdministratorSetsAdministrator(): void
    {
        $model = new Administrator();
        $this->subject->setAdministrator($model);

        self::assertSame($model, $this->subject->getAdministrator());
    }
}
