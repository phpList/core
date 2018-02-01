<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Subscription;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use PhpList\PhpList4\Domain\Model\Subscription\Subscriber;
use PhpList\PhpList4\Domain\Model\Subscription\Subscription;
use PhpList\PhpList4\Domain\Repository\Messaging\SubscriberListRepository;
use PhpList\PhpList4\Domain\Repository\Subscription\SubscriberRepository;
use PhpList\PhpList4\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PhpList\PhpList4\Tests\Integration\AbstractDatabaseTest;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberRepositoryTest extends AbstractDatabaseTest
{
    use SimilarDatesAssertionTrait;

    /**
     * @var string
     */
    const TABLE_NAME = 'phplist_user_user';

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
    const SUBSCRIBER_LIST_TABLE_NAME = 'phplist_list';

    /**
     * @var SubscriberRepository
     */
    private $subject = null;

    /**
     * @var SubscriberListRepository
     */
    private $subscriberListRepository = null;

    protected function setUp()
    {
        parent::setUp();

        $this->subject = $this->container->get(SubscriberRepository::class);
        $this->subscriberListRepository = $this->container->get(SubscriberListRepository::class);
    }

    /**
     * @test
     */
    public function findReadsModelFromDatabase()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        $creationDate = new \DateTime('2016-07-22 15:01:17');
        $modificationDate = new \DateTime('2016-08-23 19:50:43');

        /** @var Subscriber $model */
        $model = $this->subject->find($id);

        self::assertSame($id, $model->getId());
        self::assertEquals($creationDate, $model->getCreationDate());
        self::assertEquals($modificationDate, $model->getModificationDate());
        self::assertEquals('oliver@example.com', $model->getEmail());
        self::assertTrue($model->isConfirmed());
        self::assertTrue($model->isBlacklisted());
        self::assertSame(17, $model->getBounceCount());
        self::assertSame('95feb7fe7e06e6c11ca8d0c48cb46e89', $model->getUniqueId());
        self::assertTrue($model->hasHtmlEmail());
        self::assertTrue($model->isDisabled());
    }

    /**
     * @test
     */
    public function creationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->touchDatabaseTable(self::TABLE_NAME);

        $model = new Subscriber();
        $model->setEmail('sam@example.com');
        $expectedCreationDate = new \DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedCreationDate, $model->getCreationDate());
    }

    /**
     * @test
     */
    public function modificationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->touchDatabaseTable(self::TABLE_NAME);

        $model = new Subscriber();
        $model->setEmail('oliver@example.com');
        $expectedModificationDate = new \DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedModificationDate, $model->getModificationDate());
    }

    /**
     * @test
     */
    public function savePersistsAndFlushesModel()
    {
        $this->touchDatabaseTable(self::TABLE_NAME);

        $model = new Subscriber();
        $model->setEmail('michiel@example.com');
        $this->subject->save($model);

        self::assertSame($model, $this->subject->find($model->getId()));
    }

    /**
     * @test
     */
    public function emailMustBeUnique()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->applyDatabaseChanges();

        /** @var Subscriber $model */
        $model = $this->subject->find(1);

        $otherModel = new Subscriber();
        $otherModel->generateUniqueId();
        $otherModel->setEmail($model->getEmail());

        $this->expectException(UniqueConstraintViolationException::class);

        $this->subject->save($otherModel);
    }

    /**
     * @test
     */
    public function uniqueIdOfNewModelIsGeneratedOnPersist()
    {
        $this->touchDatabaseTable(self::TABLE_NAME);

        $model = new Subscriber();
        $model->setEmail('oliver@example.com');

        $this->entityManager->persist($model);

        self::assertRegExp('/^[0-9a-f]{32}$/', $model->getUniqueId());
    }

    /**
     * @test
     */
    public function persistingExistingModelKeepsUniqueIdUnchanged()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->applyDatabaseChanges();

        /** @var Subscriber $model */
        $model = $this->subject->find(1);
        $oldUniqueId = $model->getUniqueId();

        $model->setEmail('other@example.com');
        $this->entityManager->persist($model);

        self::assertSame($oldUniqueId, $model->getUniqueId());
    }

    /**
     * @test
     */
    public function findOneByEmailFindsSubscriberWithMatchingEmail()
    {
        $email = 'oliver@example.com';

        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->applyDatabaseChanges();

        /** @var Subscriber $model */
        $model = $this->subject->findOneByEmail($email);

        self::assertInstanceOf(Subscriber::class, $model);
        self::assertSame($email, $model->getEmail());
    }

    /**
     * @test
     */
    public function findOneByEmailIgnoresSubscriberWithNonMatchingEmail()
    {
        $email = 'other@example.com';

        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->applyDatabaseChanges();

        $model = $this->subject->findOneByEmail($email);

        self::assertNull($model);
    }

    /**
     * @test
     */
    public function findsAssociatedSubscriptions()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->getDataSet()->addTable(self::SUBSCRIBER_LIST_TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->getDataSet()->addTable(self::SUBSCRIPTION_TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->applyDatabaseChanges();

        /** @var Subscriber $model */
        $id = 1;
        $model = $this->subject->find($id);
        $subscriptions = $model->getSubscriptions();

        self::assertFalse($subscriptions->isEmpty());
        /** @var Subscription $firstSubscription */
        $firstSubscription = $subscriptions->first();
        self::assertInstanceOf(Subscription::class, $firstSubscription);
        $expectedSubscriberListId = 2;
        self::assertSame($expectedSubscriberListId, $firstSubscription->getSubscriberList()->getId());
    }

    /**
     * @test
     */
    public function findsAssociatedSubscribedLists()
    {
        $this->getDataSet()->addTable(self::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->getDataSet()->addTable(self::SUBSCRIBER_LIST_TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->getDataSet()->addTable(self::SUBSCRIPTION_TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->applyDatabaseChanges();

        /** @var Subscriber $model */
        $id = 1;
        $model = $this->subject->find($id);
        $subscribedLists = $model->getSubscribedLists();

        $expectedList = $this->subscriberListRepository->find(2);
        $unexpectedList = $this->subscriberListRepository->find(1);
        self::assertTrue($subscribedLists->contains($expectedList));
        self::assertFalse($subscribedLists->contains($unexpectedList));
    }
}
