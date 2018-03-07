<?php
declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Model\Subscription;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
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
    public function getEmailInitiallyReturnsEmptyString()
    {
        static::assertSame('', $this->subject->getEmail());
    }

    /**
     * @test
     */
    public function setEmailSetsEmail()
    {
        $value = 'Club-Mate';
        $this->subject->setEmail($value);

        static::assertSame($value, $this->subject->getEmail());
    }

    /**
     * @test
     */
    public function isConfirmedInitiallyReturnsFalse()
    {
        static::assertFalse($this->subject->isConfirmed());
    }

    /**
     * @test
     */
    public function setConfirmedSetsConfirmed()
    {
        $this->subject->setConfirmed(true);

        static::assertTrue($this->subject->isConfirmed());
    }

    /**
     * @test
     */
    public function isBlacklistedInitiallyReturnsFalse()
    {
        static::assertFalse($this->subject->isBlacklisted());
    }

    /**
     * @test
     */
    public function setBlacklistedSetsBlacklisted()
    {
        $this->subject->setBlacklisted(true);

        static::assertTrue($this->subject->isBlacklisted());
    }

    /**
     * @test
     */
    public function getBounceCountInitiallyReturnsZero()
    {
        static::assertSame(0, $this->subject->getBounceCount());
    }

    /**
     * @test
     */
    public function setBounceCountSetsBounceCount()
    {
        $value = 123456;
        $this->subject->setBounceCount($value);

        static::assertSame($value, $this->subject->getBounceCount());
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

        static::assertSame($initialValue + $delta, $this->subject->getBounceCount());
    }

    /**
     * @test
     */
    public function getUniqueIdInitiallyReturnsEmptyString()
    {
        static::assertSame('', $this->subject->getUniqueId());
    }

    /**
     * @test
     */
    public function setUniqueIdSetsUniqueId()
    {
        $value = 'Club-Mate';
        $this->subject->setUniqueId($value);

        static::assertSame($value, $this->subject->getUniqueId());
    }

    /**
     * @test
     */
    public function generateUniqueIdGeneratesUniqueId()
    {
        $this->subject->generateUniqueId();

        static::assertRegExp('/^[0-9a-f]{32}$/', $this->subject->getUniqueId());
    }

    /**
     * @test
     */
    public function hasHtmlEmailInitiallyReturnsFalse()
    {
        static::assertFalse($this->subject->hasHtmlEmail());
    }

    /**
     * @test
     */
    public function setHtmlEmailSetsWantsHtmlEmail()
    {
        $this->subject->setHtmlEmail(true);

        static::assertTrue($this->subject->hasHtmlEmail());
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
    public function getSubscriptionsByDefaultReturnsEmptyCollection()
    {
        $result = $this->subject->getSubscriptions();

        static::assertInstanceOf(Collection::class, $result);
        static::assertTrue($result->isEmpty());
    }

    /**
     * @test
     */
    public function setSubscriptionsSetsSubscriptions()
    {
        $subscriptions = new ArrayCollection();

        $this->subject->setSubscriptions($subscriptions);

        static::assertSame($subscriptions, $this->subject->getSubscriptions());
    }

    /**
     * @test
     */
    public function getSubscribedListsByDefaultReturnsEmptyCollection()
    {
        $result = $this->subject->getSubscribedLists();

        static::assertInstanceOf(Collection::class, $result);
        static::assertTrue($result->isEmpty());
    }

    /**
     * @test
     */
    public function setSubscribedListsSetsSubscribedLists()
    {
        $subscriptions = new ArrayCollection();

        $this->subject->setSubscribedLists($subscriptions);

        static::assertSame($subscriptions, $this->subject->getSubscribedLists());
    }
}
