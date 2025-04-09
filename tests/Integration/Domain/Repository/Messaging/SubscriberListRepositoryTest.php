<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Messaging;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Model\Subscription\SubscriberList;
use PhpList\Core\Domain\Model\Subscription\Subscription;
use PhpList\Core\Domain\Repository\Identity\AdministratorRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriberListRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriberRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriptionRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\AdministratorFixture;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\SubscriberFixture;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\SubscriberListFixture;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\SubscriptionFixture;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberListRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use SimilarDatesAssertionTrait;

    private SubscriberListRepository $subscriberListRepository;
    private AdministratorRepository $administratorRepository;
    private SubscriberRepository $subscriberRepository;
    private SubscriptionRepository $subscriptionRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->subscriberListRepository = self::getContainer()->get(SubscriberListRepository::class);
        $this->administratorRepository = self::getContainer()->get(AdministratorRepository::class);
        $this->subscriberRepository = self::getContainer()->get(SubscriberRepository::class);
        $this->subscriptionRepository = self::getContainer()->get(SubscriptionRepository::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testFindReadsModelFromDatabase()
    {
        $this->loadFixtures([SubscriberListFixture::class]);

        $id = 1;
        $creationDate = new DateTime();
        $modificationDate = new DateTime();
        $name = 'News';
        $description = 'News (and some fun stuff)';
        $listPosition = 12;
        $subjectPrefix = 'phpList';
        $category = 'news';

        /** @var SubscriberList $model */
        $model = $this->subscriberListRepository->find($id);

        self::assertSame($id, $model->getId());
        self::assertSimilarDates($creationDate, $model->getCreationDate());
        self::assertSimilarDates($modificationDate, $model->getModificationDate());
        self::assertSame($name, $model->getName());
        self::assertSame($description, $model->getDescription());
        self::assertSame($listPosition, $model->getListPosition());
        self::assertSame($subjectPrefix, $model->getSubjectPrefix());
        self::assertTrue($model->isPublic());
        self::assertSame($category, $model->getCategory());
    }

    public function testCreationDateOfNewModelIsSetToNowOnPersist()
    {
        $model = new SubscriberList();
        $expectedCreationDate = new DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedCreationDate, $model->getCreationDate());
    }

    public function testModificationDateOfNewModelIsSetToNowOnPersist()
    {
        $model = new SubscriberList();
        $expectedModificationDate = new DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedModificationDate, $model->getModificationDate());
    }

    public function testSavePersistsAndFlushesModel()
    {
        $model = new SubscriberList();
        $this->subscriberListRepository->save($model);

        self::assertSame($model, $this->subscriberListRepository->find($model->getId()));
    }

    public function testFindByOwnerFindsSubscriberListWithTheGivenOwner()
    {
        $this->loadFixtures([SubscriberListFixture::class, AdministratorFixture::class]);

        $owner = $this->administratorRepository->find(1);
        $ownedList = $this->subscriberListRepository->find(1);

        $result = $this->subscriberListRepository->findByOwner($owner);

        self::assertContains($ownedList, $result);
    }

    public function testFindByOwnerIgnoresSubscriberListWithOtherOwner()
    {
        $this->loadFixtures([SubscriberListFixture::class, AdministratorFixture::class]);

        $owner = $this->administratorRepository->find(1);
        $foreignList = $this->subscriberListRepository->find(2);

        $result = $this->subscriberListRepository->findByOwner($owner);

        self::assertNotContains($foreignList, $result);
    }

    public function testFindByOwnerIgnoresSubscriberListFromOtherOwner()
    {
        $this->loadFixtures([SubscriberListFixture::class]);

        $owner = $this->administratorRepository->find(1);
        $unownedList = $this->subscriberListRepository->find(3);

        $result = $this->subscriberListRepository->findByOwner($owner);

        self::assertNotContains($unownedList, $result);
    }

    public function testFindsAssociatedSubscriptions()
    {
        $this->loadFixtures([SubscriberListFixture::class, SubscriberFixture::class, SubscriptionFixture::class]);

        $id = 2;
        $subscriber = $this->subscriberRepository->find($id);
        /** @var Subscription[] $subscriptions */
        $subscriptions = $this->subscriptionRepository->findBySubscriberList($subscriber);

        self::assertNotEmpty($subscriptions);
        $firstSubscription = $subscriptions[0];
        self::assertInstanceOf(Subscription::class, $firstSubscription);
        $expectedSubscriberId = 1;
        self::assertSame($expectedSubscriberId, $firstSubscription->getSubscriber()->getId());
    }

    public function testFindsAssociatedSubscribers()
    {
        $this->loadFixtures([SubscriberListFixture::class, SubscriberFixture::class, SubscriptionFixture::class]);

        $id = 2;
        /** @var Subscriber[] $subscribers */
        $subscribers = $this->subscriberRepository->getSubscribersBySubscribedListId($id);

        $expectedSubscriber = $this->subscriberRepository->find(1);
        $unexpectedSubscriber = $this->subscriberRepository->find(3);
        self::assertTrue(in_array($expectedSubscriber, $subscribers, true));
        self::assertFalse(in_array($unexpectedSubscriber, $subscribers, true));
    }

    public function testRemoveAlsoRemovesAssociatedSubscriptions()
    {
        $this->loadFixtures([SubscriberListFixture::class, SubscriberFixture::class, SubscriptionFixture::class]);

        $initialNumberOfSubscriptions = count($this->subscriptionRepository->findAll());

        $id = 2;
        /** @var SubscriberList $subscriberList */
        $subscriberList = $this->subscriberListRepository->findWithSubscription($id);

        $numberOfAssociatedSubscriptions = count($subscriberList->getSubscriptions());
        self::assertGreaterThan(0, $numberOfAssociatedSubscriptions);

        $this->entityManager->remove($subscriberList);
        $this->entityManager->flush();

        $newNumberOfSubscriptions = count($this->subscriptionRepository->findAll());

        $numberOfRemovedSubscriptions = $initialNumberOfSubscriptions - $newNumberOfSubscriptions;
        self::assertSame($numberOfAssociatedSubscriptions, $numberOfRemovedSubscriptions);
    }

    public function testRemoveRemovesModel()
    {
        $this->loadFixtures([SubscriberListFixture::class]);

        /** @var SubscriberList[] $allModels */
        $allModels = $this->subscriberListRepository->findAll();
        $numberOfModelsBeforeRemove = count($allModels);
        $firstModel = $allModels[0];

        $this->subscriberListRepository->remove($firstModel);

        $numberOfModelsAfterRemove = count($this->subscriberListRepository->findAll());
        self::assertSame(1, $numberOfModelsBeforeRemove - $numberOfModelsAfterRemove);
    }
}
