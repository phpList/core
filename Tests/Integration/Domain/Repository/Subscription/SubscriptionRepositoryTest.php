<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Subscription;

use Doctrine\ORM\Proxy\Proxy;
use PhpList\PhpList4\Domain\Model\Messaging\SubscriberList;
use PhpList\PhpList4\Domain\Model\Subscription\Subscriber;
use PhpList\PhpList4\Domain\Model\Subscription\Subscription;
use PhpList\PhpList4\Domain\Repository\Messaging\SubscriberListRepository;
use PhpList\PhpList4\Domain\Repository\Subscription\SubscriberRepository;
use PhpList\PhpList4\Domain\Repository\Subscription\SubscriptionRepository;
use PhpList\PhpList4\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\PhpList4\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriptionRepositoryTest extends TestCase
{
    use DatabaseTestTrait;
    use SimilarDatesAssertionTrait;

    /**
     * @var string
     */
    const TABLE_NAME = 'phplist_listuser';

    /**
     * @var string
     */
    const ADMINISTRATOR_TABLE_NAME = 'phplist_admin';

    /**
     * @var string
     */
    const SUBSCRIBER_TABLE_NAME = 'phplist_user_user';

    /**
     * @var string
     */
    const SUBSCRIBER_LIST_TABLE_NAME = 'phplist_list';

    /**
     * @var SubscriptionRepository
     */
    private $subject = null;

    /**
     * @var SubscriberRepository
     */
    private $subscriberRepository = null;

    /**
     * @var SubscriberListRepository
     */
    private $subscriberListRepository = null;

    protected function setUp()
    {
        $this->setUpDatabaseTest();

        $this->subject = $this->container->get(SubscriptionRepository::class);

        $this->subscriberRepository = $this->container->get(SubscriberRepository::class);
        $this->subscriberListRepository = $this->container->get(SubscriberListRepository::class);
    }

    /**
     * @test
     */
    public function findAllReadsModelsFromDatabase()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->applyDatabaseChanges();

        $creationDate = new \DateTime('2016-07-22 15:01:17');
        $modificationDate = new \DateTime('2016-08-23 19:50:43');

        /** @var Subscription[] $result */
        $result = $this->subject->findAll();

        static::assertNotEmpty($result);

        $model = $result[0];
        static::assertInstanceOf(Subscription::class, $model);
        static::assertEquals($creationDate, $model->getCreationDate());
        static::assertEquals($modificationDate, $model->getModificationDate());
    }

    /**
     * @test
     */
    public function createsSubscriberAssociationAsProxy()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->getDataSet()->addTable(static::SUBSCRIBER_TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->applyDatabaseChanges();

        $subscriberId = 1;
        /** @var Subscription $model */
        $model = $this->subject->findAll()[0];
        $subscriber = $model->getSubscriber();

        static::assertInstanceOf(Subscriber::class, $subscriber);
        static::assertInstanceOf(Proxy::class, $subscriber);
        static::assertSame($subscriberId, $subscriber->getId());
    }

    /**
     * @test
     */
    public function createsSubscriberListAssociationAsProxy()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->getDataSet()->addTable(static::SUBSCRIBER_LIST_TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->applyDatabaseChanges();

        $subscriberListId = 2;
        /** @var Subscription $model */
        $model = $this->subject->findAll()[0];
        $subscriberList = $model->getSubscriberList();

        static::assertInstanceOf(SubscriberList::class, $subscriberList);
        static::assertInstanceOf(Proxy::class, $subscriberList);
        static::assertSame($subscriberListId, $subscriberList->getId());
    }

    /**
     * @test
     */
    public function creationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->getDataSet()->addTable(static::SUBSCRIBER_TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->getDataSet()->addTable(static::SUBSCRIBER_LIST_TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->touchDatabaseTable(static::TABLE_NAME);
        $this->applyDatabaseChanges();

        $model = new Subscription();
        /** @var Subscriber $subscriber */
        $subscriber = $this->subscriberRepository->find(1);
        $model->setSubscriber($subscriber);
        /** @var SubscriberList $subscriberList */
        $subscriberList = $this->subscriberListRepository->find(1);
        $model->setSubscriberList($subscriberList);
        $expectedCreationDate = new \DateTime();

        $this->entityManager->persist($model);

        static::assertSimilarDates($expectedCreationDate, $model->getCreationDate());
    }

    /**
     * @test
     */
    public function modificationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->getDataSet()->addTable(static::SUBSCRIBER_TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->getDataSet()->addTable(static::SUBSCRIBER_LIST_TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->touchDatabaseTable(static::TABLE_NAME);
        $this->applyDatabaseChanges();

        $model = new Subscription();
        /** @var Subscriber $subscriber */
        $subscriber = $this->subscriberRepository->find(1);
        $model->setSubscriber($subscriber);
        /** @var SubscriberList $subscriberList */
        $subscriberList = $this->subscriberListRepository->find(1);
        $model->setSubscriberList($subscriberList);
        $expectedModificationDate = new \DateTime();

        $this->entityManager->persist($model);

        static::assertSimilarDates($expectedModificationDate, $model->getModificationDate());
    }

    /**
     * @test
     */
    public function findBySubscriberFindsSubscriptionOnlyWithTheGivenSubscriber()
    {
        $this->getDataSet()->addTable(static::SUBSCRIBER_TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->touchDatabaseTable(static::TABLE_NAME);
        $this->applyDatabaseChanges();

        /** @var Subscriber $subscriber */
        $subscriber = $this->subscriberRepository->find(1);

        $result = $this->subject->findBySubscriber($subscriber);

        /** @var Subscription $subscription */
        foreach ($result as $subscription) {
            static::assertSame($subscriber, $subscription->getSubscriber());
        }
    }

    /**
     * @test
     */
    public function findBySubscriberListFindsSubscriptionOnlyWithTheGivenSubscriberList()
    {
        $this->getDataSet()->addTable(static::SUBSCRIBER_TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->touchDatabaseTable(static::TABLE_NAME);
        $this->applyDatabaseChanges();

        /** @var SubscriberList $subscriberList */
        $subscriberList = $this->subscriberListRepository->find(1);

        $result = $this->subject->findBySubscriberList($subscriberList);

        /** @var Subscription $subscription */
        foreach ($result as $subscription) {
            static::assertSame($subscriberList, $subscription->getSubscriberList());
        }
    }
}
