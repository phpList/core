<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Model\Subscription;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Model\Subscription\SubscriberList;
use PhpList\Core\Domain\Model\Subscription\Subscription;
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

    private Subscriber $subscriber;

    protected function setUp(): void
    {
        $this->subscriber = new Subscriber();
    }

    public function testIsDomainModel(): void
    {
        self::assertInstanceOf(DomainModel::class, $this->subscriber);
    }

    public function testGetIdReturnsId(): void
    {
        $id = 123456;
        $this->setSubjectId($this->subscriber, $id);

        self::assertSame($id, $this->subscriber->getId());
    }

    public function testGetCreatedAtInitiallyReturnsNull(): void
    {
        self::assertSimilarDates(new \DateTime(), $this->subscriber->getCreatedAt());
    }

    public function testUpdateCreationDateSetsCreationDateToNow(): void
    {
        $this->subscriber->updateUpdatedAt();

        self::assertSimilarDates(new \DateTime(), $this->subscriber->getCreatedAt());
    }

    public function testgetUpdatedAtInitiallyReturnsNull(): void
    {
        self::assertNull($this->subscriber->getUpdatedAt());
    }

    public function testUpdateModificationDateSetsModificationDateToNow(): void
    {
        $this->subscriber->updateUpdatedAt();

        self::assertSimilarDates(new \DateTime(), $this->subscriber->getUpdatedAt());
    }

    public function testGetEmailInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subscriber->getEmail());
    }

    public function testSetEmailSetsEmail(): void
    {
        $value = 'Club-Mate';
        $this->subscriber->setEmail($value);

        self::assertSame($value, $this->subscriber->getEmail());
    }

    public function testIsConfirmedInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subscriber->isConfirmed());
    }

    public function testSetConfirmedSetsConfirmed(): void
    {
        $this->subscriber->setConfirmed(true);

        self::assertTrue($this->subscriber->isConfirmed());
    }

    public function testIsBlacklistedInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subscriber->isBlacklisted());
    }

    public function testSetBlacklistedSetsBlacklisted(): void
    {
        $this->subscriber->setBlacklisted(true);

        self::assertTrue($this->subscriber->isBlacklisted());
    }

    public function testGetBounceCountInitiallyReturnsZero(): void
    {
        self::assertSame(0, $this->subscriber->getBounceCount());
    }

    public function testSetBounceCountSetsBounceCount(): void
    {
        $value = 123456;
        $this->subscriber->setBounceCount($value);

        self::assertSame($value, $this->subscriber->getBounceCount());
    }

    public function testAddToBounceCountAddsToBounceCount(): void
    {
        $initialValue = 4;
        $this->subscriber->setBounceCount($initialValue);
        $delta = 2;

        $this->subscriber->addToBounceCount($delta);

        self::assertSame($initialValue + $delta, $this->subscriber->getBounceCount());
    }

    public function testGetUniqueIdInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subscriber->getUniqueId());
    }

    public function testSetUniqueIdSetsUniqueId(): void
    {
        $value = 'Club-Mate';
        $this->subscriber->setUniqueId($value);

        self::assertSame($value, $this->subscriber->getUniqueId());
    }

    public function testGenerateUniqueIdGeneratesUniqueId(): void
    {
        $this->subscriber->generateUniqueId();

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $this->subscriber->getUniqueId());
    }

    public function testHasHtmlEmailInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subscriber->hasHtmlEmail());
    }

    public function testSetHtmlEmailSetsWantsHtmlEmail(): void
    {
        $this->subscriber->setHtmlEmail(true);

        self::assertTrue($this->subscriber->hasHtmlEmail());
    }

    public function testIsDisabledInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subscriber->isDisabled());
    }

    public function testSetDisabledSetsDisabled(): void
    {
        $this->subscriber->setDisabled(true);

        self::assertTrue($this->subscriber->isDisabled());
    }

    public function testGetExtraDataInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subscriber->getExtraData());
    }

    public function testSetExtraDataSetsExtraData(): void
    {
        $value = 'This is one of our favourite subscribers.';
        $this->subscriber->setExtraData($value);

        self::assertSame($value, $this->subscriber->getExtraData());
    }

    public function testGetSubscriptionsByDefaultReturnsEmptyCollection(): void
    {
        $result = $this->subscriber->getSubscriptions();

        self::assertInstanceOf(Collection::class, $result);
        self::assertTrue($result->isEmpty());
    }

    public function testSetSubscriptionsSetsSubscriptions(): void
    {
        $subscription = new Subscription();

        $this->subscriber->addSubscription($subscription);

        $expectedSubscriptions = new ArrayCollection([$subscription]);

        self::assertEquals($expectedSubscriptions, $this->subscriber->getSubscriptions());
    }

    public function testGetSubscribedListsByDefaultReturnsEmptyCollection(): void
    {
        $result = $this->subscriber->getSubscribedLists();

        self::assertInstanceOf(Collection::class, $result);
        self::assertTrue($result->isEmpty());
    }

    public function testSetSubscribedListsSetsSubscribedLists(): void
    {
        $subscriberList = new SubscriberList();
        $subscription = new Subscription();
        $subscription->setSubscriberList($subscriberList);

        $this->subscriber->addSubscription($subscription);

        $expectedSubscribedLists = new ArrayCollection([$subscriberList]);

        self::assertEquals($expectedSubscribedLists, $this->subscriber->getSubscribedLists());
    }
}
