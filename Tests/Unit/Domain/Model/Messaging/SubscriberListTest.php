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
    public function getNameInitiallyReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getName());
    }

    /**
     * @test
     */
    public function setNameSetsName()
    {
        $value = 'phpList releases';
        $this->subject->setName($value);

        self::assertSame($value, $this->subject->getName());
    }

    /**
     * @test
     */
    public function getDescriptionInitiallyReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getDescription());
    }

    /**
     * @test
     */
    public function setDescriptionSetsDescription()
    {
        $value = 'Subscribe to this list when you would like to be notified of new phpList releases.';
        $this->subject->setDescription($value);

        self::assertSame($value, $this->subject->getDescription());
    }

    /**
     * @test
     */
    public function getListPositionInitiallyReturnsZero()
    {
        self::assertSame(0, $this->subject->getListPosition());
    }

    /**
     * @test
     */
    public function setListPositionSetsListPosition()
    {
        $value = 123456;
        $this->subject->setListPosition($value);

        self::assertSame($value, $this->subject->getListPosition());
    }

    /**
     * @test
     */
    public function getSubjectPrefixInitiallyReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getSubjectPrefix());
    }

    /**
     * @test
     */
    public function setSubjectPrefixSetsSubjectPrefix()
    {
        $value = 'Club-Mate';
        $this->subject->setSubjectPrefix($value);

        self::assertSame($value, $this->subject->getSubjectPrefix());
    }

    /**
     * @test
     */
    public function isPublicInitiallyReturnsFalse()
    {
        self::assertFalse($this->subject->isPublic());
    }

    /**
     * @test
     */
    public function setPublicSetsPublic()
    {
        $this->subject->setPublic(true);

        self::assertTrue($this->subject->isPublic());
    }

    /**
     * @test
     */
    public function getCategoryInitiallyReturnsEmptyString()
    {
        self::assertSame('', $this->subject->getCategory());
    }

    /**
     * @test
     */
    public function setCategorySetsCategory()
    {
        $value = 'Club-Mate';
        $this->subject->setCategory($value);

        self::assertSame($value, $this->subject->getCategory());
    }

    /**
     * @test
     */
    public function getOwnerInitiallyReturnsNull()
    {
        self::assertNull($this->subject->getOwner());
    }

    /**
     * @test
     */
    public function setOwnerSetsOwner()
    {
        $model = new Administrator();
        $this->subject->setOwner($model);

        self::assertSame($model, $this->subject->getOwner());
    }

    /**
     * @test
     */
    public function getSubscriptionsByDefaultReturnsEmptyCollection()
    {
        $result = $this->subject->getSubscriptions();

        self::assertInstanceOf(Collection::class, $result);
        self::assertTrue($result->isEmpty());
    }

    /**
     * @test
     */
    public function setSubscriptionsSetsSubscriptions()
    {
        $subscriptions = new ArrayCollection();

        $this->subject->setSubscriptions($subscriptions);

        self::assertSame($subscriptions, $this->subject->getSubscriptions());
    }
}
