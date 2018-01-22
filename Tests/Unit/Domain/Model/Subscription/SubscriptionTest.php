<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Unit\Domain\Model\Subscription;

use PhpList\PhpList4\Domain\Model\Messaging\SubscriberList;
use PhpList\PhpList4\Domain\Model\Subscription\Subscriber;
use PhpList\PhpList4\Domain\Model\Subscription\Subscription;
use PhpList\PhpList4\TestingSupport\Traits\ModelTestTrait;
use PhpList\PhpList4\TestingSupport\Traits\SimilarDatesAssertionTrait;
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

    /**
     * @var Subscription
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = new Subscription();
    }

    /**
     * @test
     */
    public function getSubscriberInitiallyReturnsNull()
    {
        self::assertNull($this->subject->getSubscriber());
    }

    /**
     * @test
     */
    public function setSubscriberSetsSubscriber()
    {
        $model = new Subscriber();
        $this->subject->setSubscriber($model);

        self::assertSame($model, $this->subject->getSubscriber());
    }

    /**
     * @test
     */
    public function getSubscriberListInitiallyReturnsNull()
    {
        self::assertNull($this->subject->getSubscriberList());
    }

    /**
     * @test
     */
    public function setSubscriberListSetsSubscriberList()
    {
        $model = new SubscriberList();
        $this->subject->setSubscriberList($model);

        self::assertSame($model, $this->subject->getSubscriberList());
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
}
