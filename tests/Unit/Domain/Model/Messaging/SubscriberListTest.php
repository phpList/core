<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Model\Messaging;

use DateTime;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Interfaces\DomainModel;
use PhpList\Core\Domain\Model\Messaging\SubscriberList;
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

    private SubscriberList $subject;

    protected function setUp(): void
    {
        $this->subject = new SubscriberList();
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

    public function testGetModificationDateInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getModificationDate());
    }

    public function testUpdateModificationDateSetsModificationDateToNow(): void
    {
        $this->subject->updateModificationDate();

        self::assertSimilarDates(new DateTime(), $this->subject->getModificationDate());
    }

    public function testGetNameInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getName());
    }

    public function testSetNameSetsName(): void
    {
        $value = 'phpList releases';
        $this->subject->setName($value);

        self::assertSame($value, $this->subject->getName());
    }

    public function testGetDescriptionInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getDescription());
    }

    public function testSetDescriptionSetsDescription(): void
    {
        $value = 'Subscribe to this list when you would like to be notified of new phpList releases.';
        $this->subject->setDescription($value);

        self::assertSame($value, $this->subject->getDescription());
    }

    public function testGetListPositionInitiallyReturnsZero(): void
    {
        self::assertSame(0, $this->subject->getListPosition());
    }

    public function testSetListPositionSetsListPosition(): void
    {
        $value = 123456;
        $this->subject->setListPosition($value);

        self::assertSame($value, $this->subject->getListPosition());
    }

    public function testGetSubjectPrefixInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getSubjectPrefix());
    }

    public function testSetSubjectPrefixSetsSubjectPrefix(): void
    {
        $value = 'Club-Mate';
        $this->subject->setSubjectPrefix($value);

        self::assertSame($value, $this->subject->getSubjectPrefix());
    }

    public function testIsPublicInitiallyReturnsFalse(): void
    {
        self::assertFalse($this->subject->isPublic());
    }

    public function testSetPublicSetsPublic(): void
    {
        $this->subject->setPublic(true);

        self::assertTrue($this->subject->isPublic());
    }

    public function testGetCategoryInitiallyReturnsEmptyString(): void
    {
        self::assertSame('', $this->subject->getCategory());
    }

    public function testSetCategorySetsCategory(): void
    {
        $value = 'Club-Mate';
        $this->subject->setCategory($value);

        self::assertSame($value, $this->subject->getCategory());
    }

    public function testGetOwnerInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getOwner());
    }

    public function testSetOwnerSetsOwner(): void
    {
        $model = new Administrator();
        $this->subject->setOwner($model);

        self::assertSame($model, $this->subject->getOwner());
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

    public function testGetSubscribersByDefaultReturnsEmptyCollection(): void
    {
        $result = $this->subject->getSubscribers();

        self::assertInstanceOf(Collection::class, $result);
        self::assertTrue($result->isEmpty());
    }

    public function testSetSubscribersSetsSubscribers(): void
    {
        $subscriptions = new ArrayCollection();

        $this->subject->setSubscribers($subscriptions);

        self::assertSame($subscriptions, $this->subject->getSubscribers());
    }
}
