<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Domain\Model\Messaging;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Model\Messaging\SubscriberList;
use PhpList\PhpList4\TestingSupport\Traits\ModelTestTrait;
use PhpList\PhpList4\TestingSupport\Traits\SimilarDatesAssertionTrait;
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

    /**
     * @var SubscriberList
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new SubscriberList();
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
    public function getNameInitiallyReturnsEmptyString()
    {
        static::assertSame('', $this->subject->getName());
    }

    /**
     * @test
     */
    public function setNameSetsName()
    {
        $value = 'phpList releases';
        $this->subject->setName($value);

        static::assertSame($value, $this->subject->getName());
    }

    /**
     * @test
     */
    public function getDescriptionInitiallyReturnsEmptyString()
    {
        static::assertSame('', $this->subject->getDescription());
    }

    /**
     * @test
     */
    public function setDescriptionSetsDescription()
    {
        $value = 'Subscribe to this list when you would like to be notified of new phpList releases.';
        $this->subject->setDescription($value);

        static::assertSame($value, $this->subject->getDescription());
    }

    /**
     * @test
     */
    public function getListPositionInitiallyReturnsZero()
    {
        static::assertSame(0, $this->subject->getListPosition());
    }

    /**
     * @test
     */
    public function setListPositionSetsListPosition()
    {
        $value = 123456;
        $this->subject->setListPosition($value);

        static::assertSame($value, $this->subject->getListPosition());
    }

    /**
     * @test
     */
    public function getSubjectPrefixInitiallyReturnsEmptyString()
    {
        static::assertSame('', $this->subject->getSubjectPrefix());
    }

    /**
     * @test
     */
    public function setSubjectPrefixSetsSubjectPrefix()
    {
        $value = 'Club-Mate';
        $this->subject->setSubjectPrefix($value);

        static::assertSame($value, $this->subject->getSubjectPrefix());
    }

    /**
     * @test
     */
    public function isPublicInitiallyReturnsFalse()
    {
        static::assertFalse($this->subject->isPublic());
    }

    /**
     * @test
     */
    public function setPublicSetsPublic()
    {
        $this->subject->setPublic(true);

        static::assertTrue($this->subject->isPublic());
    }

    /**
     * @test
     */
    public function getCategoryInitiallyReturnsEmptyString()
    {
        static::assertSame('', $this->subject->getCategory());
    }

    /**
     * @test
     */
    public function setCategorySetsCategory()
    {
        $value = 'Club-Mate';
        $this->subject->setCategory($value);

        static::assertSame($value, $this->subject->getCategory());
    }

    /**
     * @test
     */
    public function getOwnerInitiallyReturnsNull()
    {
        static::assertNull($this->subject->getOwner());
    }

    /**
     * @test
     */
    public function setOwnerSetsOwner()
    {
        $model = new Administrator();
        $this->subject->setOwner($model);

        static::assertSame($model, $this->subject->getOwner());
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
    public function getSubscribersByDefaultReturnsEmptyCollection()
    {
        $result = $this->subject->getSubscribers();

        static::assertInstanceOf(Collection::class, $result);
        static::assertTrue($result->isEmpty());
    }

    /**
     * @test
     */
    public function setSubscribersSetsSubscribers()
    {
        $subscriptions = new ArrayCollection();

        $this->subject->setSubscribers($subscriptions);

        static::assertSame($subscriptions, $this->subject->getSubscribers());
    }
}
