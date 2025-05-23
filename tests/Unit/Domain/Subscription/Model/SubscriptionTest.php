<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Model;

use DateTime;
use PhpList\Core\Domain\Common\Model\Interfaces\DomainModel;
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

    public function testGetCreatedAtInitiallyReturnsCurrentTime(): void
    {
        self::assertSimilarDates(new DateTime(), $this->subject->getCreatedAt());
    }

    public function testGetUpdatedAtInitiallyReturnsNull(): void
    {
        self::assertNull($this->subject->getUpdatedAt());
    }

    public function testUpdateModificationDateSetsModificationDateToNow(): void
    {
        $this->subject->updateUpdatedAt();

        self::assertSimilarDates(new DateTime(), $this->subject->getUpdatedAt());
    }
}
