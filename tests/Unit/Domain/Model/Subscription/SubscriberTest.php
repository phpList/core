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

    private Subscriber $subject;

    protected function setUp(): void
    {
        $this->subject = new Subscriber();
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

    public function testGetEmailInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getEmail());
    }

    public function testSetEmailSetsEmail(): void
    {
        $value = 'Club-Mate';
        $this->subject->setEmail($value);

        self::assertSame($value, $this->subject->getEmail());
    }

    public function testIsConfirmedInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->isConfirmed());
    }

    public function testSetConfirmedSetsConfirmed(): void
    {
        $this->subject->setConfirmed(true);

        self::assertTrue($this->subject->isConfirmed());
    }

    public function testIsBlacklistedInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->isBlacklisted());
    }

    public function testSetBlacklistedSetsBlacklisted(): void
    {
        $this->subject->setBlacklisted(true);

        self::assertTrue($this->subject->isBlacklisted());
    }

    public function testGetBounceCountInitiallyReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getBounceCount());
    }

    public function testSetBounceCountSetsBounceCount(): void
    {
        $value = 123456;
        $this->subject->setBounceCount($value);

        self::assertSame($value, $this->subject->getBounceCount());
    }

    public function testAddToBounceCountAddsToBounceCount(): void
    {
        $initialValue = 4;
        $this->subject->setBounceCount($initialValue);
        $delta = 2;

        $this->subject->addToBounceCount($delta);

        self::assertSame($initialValue + $delta, $this->subject->getBounceCount());
    }

    public function testGetUniqueIdInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getUniqueId());
    }

    public function testSetUniqueIdSetsUniqueId(): void
    {
        $value = 'Club-Mate';
        $this->subject->setUniqueId($value);

        self::assertSame($value, $this->subject->getUniqueId());
    }

    public function testGenerateUniqueIdGeneratesUniqueId(): void
    {
        $this->subject->generateUniqueId();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $this->subject->getUniqueId());
    }

    public function testHasHtmlEmailInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->hasHtmlEmail());
    }

    public function testSetHtmlEmailSetsWantsHtmlEmail(): void
    {
        $this->subject->setHtmlEmail(true);

        self::assertTrue($this->subject->hasHtmlEmail());
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

    public function testGetExtraDataInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getExtraData());
    }

    public function testSetExtraDataSetsExtraData(): void
    {
        $value = 'This is one of our favourite subscribers.';
        $this->subject->setExtraData($value);

        self::assertSame($value, $this->subject->getExtraData());
    }

    public function testGetSubscriptionsByDefaultReturnsEmptyCollection(): void
    {
        $result = $this->subject->getSubscriptions();

        self::assertInstanceOf(Collection::class, $result);
        self::assertTrue($result->isEmpty());
    }

    public function testSetSubscriptionsSetsSubscriptions(): void
    {
        $subscriptions = new ArrayCollection();

        $this->subject->setSubscriptions($subscriptions);

        self::assertSame($subscriptions, $this->subject->getSubscriptions());
    }

    public function testGetSubscribedListsByDefaultReturnsEmptyCollection(): void
    {
        $result = $this->subject->getSubscribedLists();

        self::assertInstanceOf(Collection::class, $result);
        self::assertTrue($result->isEmpty());
    }

    public function testSetSubscribedListsSetsSubscribedLists(): void
    {
        $subscriptions = new ArrayCollection();

        $this->subject->setSubscribedLists($subscriptions);

        self::assertSame($subscriptions, $this->subject->getSubscribedLists());
    }
}
