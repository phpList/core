<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Model;

use DateTime;
use Doctrine\Common\Collections\Collection;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Model\Subscription;
use PhpList\Core\TestingSupport\Traits\ModelTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberListTest extends TestCase
{
    use ModelTestTrait;
    use SimilarDatesAssertionTrait;

    private SubscriberList $subscriberList;

    protected function setUp(): void
    {
        $this->subscriberList = new SubscriberList();
    }

    public function testIsDomainModel(): void
    {
        self::assertInstanceOf(DomainModel::class, $this->subscriberList);
    }

    public function testGetIdReturnsId(): void
    {
        $id = 123456;
        $this->setSubjectId($this->subscriberList, $id);

        self::assertSame($id, $this->subscriberList->getId());
    }

    public function testUpdateCreationDateSetsCreationDateToNow(): void
    {
        $this->subscriberList->setCategory('test');

        self::assertSimilarDates(new DateTime(), $this->subscriberList->getCreatedAt());
    }

    public function testgetUpdatedAtInitiallyReturnsNull(): void
    {
        self::assertNull($this->subscriberList->getUpdatedAt());
    }

    public function testUpdateModificationDateSetsModificationDateToNow(): void
    {
        $this->subscriberList->updateUpdatedAt();

        self::assertSimilarDates(new DateTime(), $this->subscriberList->getUpdatedAt());
    }

    public function testGetNameInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subscriberList->getName());
    }

    public function testSetNameSetsName(): void
    {
        $value = 'phpList releases';
        $this->subscriberList->setName($value);

        self::assertSame($value, $this->subscriberList->getName());
    }

    public function testGetDescriptionInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subscriberList->getDescription());
    }

    public function testSetDescriptionSetsDescription(): void
    {
        $value = 'Subscribe to this list when you would like to be notified of new phpList releases.';
        $this->subscriberList->setDescription($value);

        self::assertSame($value, $this->subscriberList->getDescription());
    }

    public function testGetListPositionInitiallyReturnsZero(): void
    {
        self::assertSame(0, $this->subscriberList->getListPosition());
    }

    public function testSetListPositionSetsListPosition(): void
    {
        $value = 123456;
        $this->subscriberList->setListPosition($value);

        self::assertSame($value, $this->subscriberList->getListPosition());
    }

    public function testGetSubjectPrefixInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subscriberList->getSubjectPrefix());
    }

    public function testSetSubjectPrefixSetsSubjectPrefix(): void
    {
        $value = 'Club-Mate';
        $this->subscriberList->setSubjectPrefix($value);

        self::assertSame($value, $this->subscriberList->getSubjectPrefix());
    }

    public function testIsPublicInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subscriberList->isPublic());
    }

    public function testSetPublicSetsPublic(): void
    {
        $this->subscriberList->setPublic(true);

        self::assertTrue($this->subscriberList->isPublic());
    }

    public function testGetCategoryInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subscriberList->getCategory());
    }

    public function testSetCategorySetsCategory(): void
    {
        $value = 'Club-Mate';
        $this->subscriberList->setCategory($value);

        self::assertSame($value, $this->subscriberList->getCategory());
    }

    public function testGetOwnerInitiallyReturnsNull(): void
    {
        self::assertNull($this->subscriberList->getOwner());
    }

    public function testSetOwnerSetsOwner(): void
    {
        $model = new Administrator();
        $this->subscriberList->setOwner($model);

        self::assertSame($model, $this->subscriberList->getOwner());
    }

    public function testGetSubscriptionsByDefaultReturnsEmptyCollection(): void
    {
        $result = $this->subscriberList->getSubscriptions();

        self::assertInstanceOf(Collection::class, $result);
        self::assertTrue($result->isEmpty());
    }

    public function testAddSubscriptionsSetsSubscriptions(): void
    {
        $subscription = new Subscription();

        $this->subscriberList->addSubscription($subscription);

        self::assertTrue($this->subscriberList->getSubscriptions()->contains($subscription));
    }

    public function testGetSubscribersByDefaultReturnsEmptyCollection(): void
    {
        $result = $this->subscriberList->getSubscribers();

        self::assertInstanceOf(Collection::class, $result);
        self::assertTrue($result->isEmpty());
    }

    public function testSetSubscribersSetsSubscribers(): void
    {
        $subscriber = new Subscriber();
        $subscription = new Subscription();
        $subscription->setSubscriber($subscriber);

        $this->subscriberList->addSubscription($subscription);

        self::assertTrue($this->subscriberList->getSubscribers()->contains($subscriber));
    }
}
