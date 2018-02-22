<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Domain\Repository\Subscription;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
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
class SubscriberRepositoryTest extends TestCase
{
    use DatabaseTestTrait;
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

    /**
     * @var SubscriptionRepository
     */
    private $subscriptionRepository = null;

    protected function setUp()
    {
        $this->setUpDatabaseTest();

        $this->subject = $this->container->get(SubscriberRepository::class);
        $this->subscriberListRepository = $this->container->get(SubscriberListRepository::class);
        $this->subscriptionRepository = $this->container->get(SubscriptionRepository::class);
    }

    /**
     * @test
     */
    public function findReadsModelFromDatabase()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->applyDatabaseChanges();

        $id = 1;
        $creationDate = new \DateTime('2016-07-22 15:01:17');
        $modificationDate = new \DateTime('2016-08-23 19:50:43');

        /** @var Subscriber $model */
        $model = $this->subject->find($id);

        static::assertSame($id, $model->getId());
        static::assertEquals($creationDate, $model->getCreationDate());
        static::assertEquals($modificationDate, $model->getModificationDate());
        static::assertEquals('oliver@example.com', $model->getEmail());
        static::assertTrue($model->isConfirmed());
        static::assertTrue($model->isBlacklisted());
        static::assertSame(17, $model->getBounceCount());
        static::assertSame('95feb7fe7e06e6c11ca8d0c48cb46e89', $model->getUniqueId());
        static::assertTrue($model->hasHtmlEmail());
        static::assertTrue($model->isDisabled());
    }

    /**
     * @test
     */
    public function creationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->touchDatabaseTable(static::TABLE_NAME);

        $model = new Subscriber();
        $model->setEmail('sam@example.com');
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

        $model = new Subscriber();
        $model->setEmail('oliver@example.com');
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

        $model = new Subscriber();
        $model->setEmail('michiel@example.com');
        $this->subject->save($model);

        static::assertSame($model, $this->subject->find($model->getId()));
    }

    /**
     * @test
     */
    public function emailMustBeUnique()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
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
        $this->touchDatabaseTable(static::TABLE_NAME);

        $model = new Subscriber();
        $model->setEmail('oliver@example.com');

        $this->entityManager->persist($model);

        static::assertRegExp('/^[0-9a-f]{32}$/', $model->getUniqueId());
    }

    /**
     * @test
     */
    public function persistingExistingModelKeepsUniqueIdUnchanged()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->applyDatabaseChanges();

        /** @var Subscriber $model */
        $model = $this->subject->find(1);
        $oldUniqueId = $model->getUniqueId();

        $model->setEmail('other@example.com');
        $this->entityManager->persist($model);

        static::assertSame($oldUniqueId, $model->getUniqueId());
    }

    /**
     * @test
     */
    public function findOneByEmailFindsSubscriberWithMatchingEmail()
    {
        $email = 'oliver@example.com';

        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->applyDatabaseChanges();

        /** @var Subscriber $model */
        $model = $this->subject->findOneByEmail($email);

        static::assertInstanceOf(Subscriber::class, $model);
        static::assertSame($email, $model->getEmail());
    }

    /**
     * @test
     */
    public function findOneByEmailIgnoresSubscriberWithNonMatchingEmail()
    {
        $email = 'other@example.com';

        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->applyDatabaseChanges();

        $model = $this->subject->findOneByEmail($email);

        static::assertNull($model);
    }

    /**
     * @test
     */
    public function findsAssociatedSubscriptions()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->getDataSet()->addTable(static::SUBSCRIBER_LIST_TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->getDataSet()->addTable(static::SUBSCRIPTION_TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->applyDatabaseChanges();

        /** @var Subscriber $model */
        $id = 1;
        $model = $this->subject->find($id);
        $subscriptions = $model->getSubscriptions();

        static::assertFalse($subscriptions->isEmpty());
        /** @var Subscription $firstSubscription */
        $firstSubscription = $subscriptions->first();
        static::assertInstanceOf(Subscription::class, $firstSubscription);
        $expectedSubscriberListId = 2;
        static::assertSame($expectedSubscriberListId, $firstSubscription->getSubscriberList()->getId());
    }

    /**
     * @test
     */
    public function findsAssociatedSubscribedLists()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->getDataSet()->addTable(static::SUBSCRIBER_LIST_TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->getDataSet()->addTable(static::SUBSCRIPTION_TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->applyDatabaseChanges();

        /** @var Subscriber $model */
        $id = 1;
        $model = $this->subject->find($id);
        $subscribedLists = $model->getSubscribedLists();

        $expectedList = $this->subscriberListRepository->find(2);
        $unexpectedList = $this->subscriberListRepository->find(1);
        static::assertTrue($subscribedLists->contains($expectedList));
        static::assertFalse($subscribedLists->contains($unexpectedList));
    }

    /**
     * @test
     */
    public function removeAlsoRemovesAssociatedSubscriptions()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->getDataSet()->addTable(static::SUBSCRIBER_LIST_TABLE_NAME, __DIR__ . '/../Fixtures/SubscriberList.csv');
        $this->getDataSet()->addTable(static::SUBSCRIPTION_TABLE_NAME, __DIR__ . '/../Fixtures/Subscription.csv');
        $this->applyDatabaseChanges();

        $initialNumberOfSubscriptions = count($this->subscriptionRepository->findAll());

        /** @var Subscriber $model */
        $id = 2;
        $model = $this->subject->find($id);

        $numberOfAssociatedSubscriptions = count($model->getSubscriptions());
        static::assertGreaterThan(0, $numberOfAssociatedSubscriptions);

        $this->entityManager->remove($model);
        $this->entityManager->flush();

        $newNumberOfSubscriptions = count($this->subscriptionRepository->findAll());
        $numberOfRemovedSubscriptions = $initialNumberOfSubscriptions - $newNumberOfSubscriptions;
        static::assertSame($numberOfAssociatedSubscriptions, $numberOfRemovedSubscriptions);
    }

    /**
     * @test
     */
    public function removeRemovesModel()
    {
        $this->getDataSet()->addTable(static::TABLE_NAME, __DIR__ . '/../Fixtures/Subscriber.csv');
        $this->applyDatabaseChanges();

        /** @var Subscriber[] $allModels */
        $allModels = $this->subject->findAll();
        $numberOfModelsBeforeRemove = count($allModels);
        $firstModel = $allModels[0];

        $this->subject->remove($firstModel);

        $numberOfModelsAfterRemove = count($this->subject->findAll());
        static::assertSame(1, $numberOfModelsBeforeRemove - $numberOfModelsAfterRemove);
    }
}
