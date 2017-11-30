<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Domain\Model\Subscription;

use PhpList\PhpList4\Domain\Model\Subscription\Subscriber;
use PhpList\PhpList4\Tests\Support\Traits\ModelTestTrait;
use PhpList\PhpList4\Tests\Support\Traits\SimilarDatesAssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberTest extends TestCase
{
    use ModelTestTrait;
    use SimilarDatesAssertionTrait;

    /**
     * @var Subscriber
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new Subscriber();
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
    public function getEmailInitiallyReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getEmail());
    }

    /**
     * @test
     */
    public function setEmailSetsEmail()
    {
        $value = 'Club-Mate';
        $this->subject->setEmail($value);

        self::assertSame($value, $this->subject->getEmail());
    }

    /**
     * @test
     */
    public function isConfirmedInitiallyReturnsFalse()
    {
        self::assertFalse($this->subject->isConfirmed());
    }

    /**
     * @test
     */
    public function setConfirmedSetsConfirmed()
    {
        $this->subject->setConfirmed(true);

        self::assertTrue($this->subject->isConfirmed());
    }

    /**
     * @test
     */
    public function isBlacklistedInitiallyReturnsFalse()
    {
        self::assertFalse($this->subject->isBlacklisted());
    }

    /**
     * @test
     */
    public function setBlacklistedSetsBlacklisted()
    {
        $this->subject->setBlacklisted(true);

        self::assertTrue($this->subject->isBlacklisted());
    }

    /**
     * @test
     */
    public function getBounceCountInitiallyReturnsZero()
    {
        self::assertSame(0, $this->subject->getBounceCount());
    }

    /**
     * @test
     */
    public function setBounceCountSetsBounceCount()
    {
        $value = 123456;
        $this->subject->setBounceCount($value);

        self::assertSame($value, $this->subject->getBounceCount());
    }

    /**
     * @test
     */
    public function addToBounceCountAddsToBounceCount()
    {
        $initialValue = 4;
        $this->subject->setBounceCount($initialValue);
        $delta = 2;

        $this->subject->addToBounceCount($delta);

        self::assertSame($initialValue + $delta, $this->subject->getBounceCount());
    }

    /**
     * @test
     */
    public function getUniqueIdInitiallyReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getUniqueId());
    }

    /**
     * @test
     */
    public function setUniqueIdSetsUniqueId()
    {
        $value = 'Club-Mate';
        $this->subject->setUniqueId($value);

        self::assertSame($value, $this->subject->getUniqueId());
    }

    /**
     * @test
     */
    public function generateUniqueIdGeneratesUniqueId()
    {
        $this->subject->generateUniqueId();

        self::assertRegExp('/^[0-9a-f]{32}$/', $this->subject->getUniqueId());
    }

    /**
     * @test
     */
    public function hasHtmlEmailInitiallyReturnsFalse()
    {
        self::assertFalse($this->subject->hasHtmlEmail());
    }

    /**
     * @test
     */
    public function setHtmlEmailSetsWantsHtmlEmail()
    {
        $this->subject->setHtmlEmail(true);

        self::assertTrue($this->subject->hasHtmlEmail());
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
