<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Messaging;

use Doctrine\ORM\Proxy\Proxy;
use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Domain\Model\Messaging\SubscriberList;
use PhpList\PhpList4\Domain\Model\Subscription\Subscription;
use PhpList\PhpList4\Domain\Repository\Identity\AdministratorRepository;
use PhpList\PhpList4\Domain\Repository\Messaging\SubscriberListRepository;
use PhpList\PhpList4\Domain\Repository\Subscription\SubscriberRepository;
use PhpList\PhpList4\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PhpList\PhpList4\Tests\Integration\AbstractDatabaseTest;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberListRepositoryTest extends AbstractDatabaseTest
{
    use SimilarDatesAssertionTrait;

    /**
     * @var string
     */
    const TABLE_NAME = 'phplist_list';

    /**
     * @var string
     */
    const ADMINISTRATOR_TABLE_NAME = 'phplist_admin';

    /**
     * @var string
     */
    const SUBSCRIPTION_TABLE_NAME = 'phplist_listuser';

    /**
     * @var string
     */
    const SUBSCRIBER_TABLE_NAME = 'phplist_user_user';

    /**
     * @var SubscriberListRepository
     */
    private $subject = null;

    /**
     * @var AdministratorRepository
     */
    private $administratorRepository = null;

    /**
     * @var SubscriberRepository
     */
    private $subscriberRepository = null;

    protected function setUp()
    {
        parent::setUp();

        $this->subject = $this->container->get(SubscriberListRepository::class);
        $this->administratorRepository = $this->container->get(AdministratorRepository::class);
        $this->subscriberRepository = $this->container->get(SubscriberRepository::class);
    }

    /**
     * @test
     */
    public function findReadsModelFromDatabase()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        $creationDate = new \DateTime('2016-06-22 15:01:17');
        $modificationDate = new \DateTime('2016-06-23 19:50:43');
        $name = 'News';
        $description = 'News (and some fun stuff)';
        $listPosition = 12;
        $subjectPrefix = 'phpList';
        $category = 'news';

        /** @var SubscriberList $model */
        $model = $this->subject->find($id);

        static::assertSame($id, $model->getId());
        static::assertEquals($creationDate, $model->getCreationDate());
        static::assertEquals($modificationDate, $model->getModificationDate());
        static::assertSame($name, $model->getName());
        static::assertSame($description, $model->getDescription());
        static::assertSame($listPosition, $model->getListPosition());
        static::assertSame($subjectPrefix, $model->getSubjectPrefix());
        static::assertTrue($model->isPublic());
        static::assertSame($category, $model->getCategory());
    }

    /**
     * @test
     */
    public function createsOwnerAssociationAsProxy()
    {
        $this->getDataSet()->addTable(static::ADMINISTRATOR_TABLE_NAME, __DIR__ . '/../Fixtures/Administrator.csv');
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->applyDatabaseChanges();

        $subscriberListId = 1;
        $ownerId = 1;
        /** @var SubscriberList $model */
        $model = $this->subject->find($subscriberListId);
        $owner = $model->getOwner();

        static::assertInstanceOf(Administrator::class, $owner);
        static::assertInstanceOf(Proxy::class, $owner);
        static::assertSame($ownerId, $owner->getId());
    }

    /**
     * @test
     */
    public function creationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->touchDatabaseTable(static::TABLE_NAME);

        $model = new SubscriberList();
        $expectedCreationDate = new \DateTime();

        $this->entityManager->persist($model);

        static::assertSimilarDates($expectedCreationDate, $model->getCreationDate());
    }

    /**
     * @test
     */
    public function modificationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->touchDatabaseTable(static::TABLE_NAME);

        $model = new SubscriberList();
        $expectedModificationDate = new \DateTime();

        $this->entityManager->persist($model);

        static::assertSimilarDates($expectedModificationDate, $model->getModificationDate());
    }

    /**
     * @test
     */
    public function savePersistsAndFlushesModel()
    {
        $this->touchDatabaseTable(static::TABLE_NAME);

        $model = new SubscriberList();
        $this->subject->save($model);

        static::assertSame($model, $this->subject->find($model->getId()));
    }

    /**
     * @test
     */
    public function findByOwnerFindsSubscriberListWithTheGivenOwner()
    {
        $this->getDataSet()->addTable(static::ADMINISTRATOR_TABLE_NAME, __DIR__ . '/../Fixtures/Administrator.csv');
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->applyDatabaseChanges();

        $owner = $this->administratorRepository->find(1);
        $ownedList = $this->subject->find(1);

        $result = $this->subject->findByOwner($owner);

        static::assertContains($ownedList, $result);
    }

    /**
     * @test
     */
    public function findByOwnerIgnoresSubscriberListWithOtherOwner()
    {
        $this->getDataSet()->addTable(static::ADMINISTRATOR_TABLE_NAME, __DIR__ . '/../Fixtures/Administrator.csv');
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->applyDatabaseChanges();

        $owner = $this->administratorRepository->find(1);
        $foreignList = $this->subject->find(2);

        $result = $this->subject->findByOwner($owner);

        static::assertNotContains($foreignList, $result);
    }

    /**
     * @test
     */
    public function findByOwnerIgnoresSubscriberListFromOtherOwner()
    {
        $this->getDataSet()->addTable(static::ADMINISTRATOR_TABLE_NAME, __DIR__ . '/../Fixtures/Administrator.csv');
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->applyDatabaseChanges();

        $owner = $this->administratorRepository->find(1);
        $unownedList = $this->subject->find(3);

        $result = $this->subject->findByOwner($owner);

        static::assertNotContains($unownedList, $result);
    }

    /**
     * @test
     */
    public function findsAssociatedSubscriptions()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->getDataSet()->addTable(static::SUBSCRIBER_TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->getDataSet()->addTable(static::SUBSCRIPTION_TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->applyDatabaseChanges();

        /** @var SubscriberList $model */
        $id = 2;
        $model = $this->subject->find($id);
        $subscriptions = $model->getSubscriptions();

        static::assertFalse($subscriptions->isEmpty());
        /** @var Subscription $firstSubscription */
        $firstSubscription = $subscriptions->first();
        static::assertInstanceOf(Subscription::class, $firstSubscription);
        $expectedSubscriberId = 1;
        static::assertSame($expectedSubscriberId, $firstSubscription->getSubscriber()->getId());
    }

    /**
     * @test
     */
    public function findsAssociatedSubscribers()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->getDataSet()->addTable(static::SUBSCRIBER_TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->getDataSet()->addTable(static::SUBSCRIPTION_TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->applyDatabaseChanges();

        /** @var SubscriberList $model */
        $id = 2;
        $model = $this->subject->find($id);
        $subscribers = $model->getSubscribers();

        $expectedSubscriber = $this->subscriberRepository->find(1);
        $unexpectedSubscriber = $this->subscriberRepository->find(3);
        static::assertTrue($subscribers->contains($expectedSubscriber));
        static::assertFalse($subscribers->contains($unexpectedSubscriber));
    }
}
