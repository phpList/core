<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Model\Subscription;

use DateTime;
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
class SubscriptionTest extends TestCase
{
    use ModelTestTrait;
    use SimilarDatesAssertionTrait;

    private Subscription $subject;

    protected function setUp(): void
    {
        $this->subject = new Subscription();
    }

    public function testIsDomainModel(): void
    {
        self::assertInstanceOf(DomainModel::class, $this->subject);
    }

    public function testGetSubscriberInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getSubscriber());
    }

    public function testSetSubscriberSetsSubscriber(): void
    {
        $model = new Subscriber();
        $this->subject->setSubscriber($model);

        self::assertSame($model, $this->subject->getSubscriber());
    }

    public function testGetSubscriberListInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getSubscriberList());
    }

    public function testSetSubscriberListSetsSubscriberList(): void
    {
        $model = new SubscriberList();
        $this->subject->setSubscriberList($model);

        self::assertSame($model, $this->subject->getSubscriberList());
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
}
