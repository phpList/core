<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Subscription\Repository;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Model\Subscription;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriptionRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PhpList\Core\Tests\Integration\Domain\Subscription\Fixtures\SubscriberFixture;
use PhpList\Core\Tests\Integration\Domain\Subscription\Fixtures\SubscriberListFixture;
use PhpList\Core\Tests\Integration\Domain\Subscription\Fixtures\SubscriptionFixture;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriptionRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use SimilarDatesAssertionTrait;

    private ?SubscriptionRepository $subscriptionRepository = null;
    private ?SubscriberRepository $subscriberRepository = null;
    private ?SubscriberListRepository $subscriberListRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->subscriptionRepository = self::getContainer()->get(SubscriptionRepository::class);
        $this->subscriberRepository = self::getContainer()->get(SubscriberRepository::class);
        $this->subscriberListRepository = self::getContainer()->get(SubscriberListRepository::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testFindAllReadsModelsFromDatabase()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        $creationDate = new DateTime('2016-07-22 15:01:17');
        $modificationDate = new DateTime('2016-08-23 19:50:43');

        /** @var Subscription[] $result */
        $result = $this->subscriptionRepository->findAll();

        self::assertNotEmpty($result);

        $model = $result[0];
        self::assertInstanceOf(Subscription::class, $model);
        self::assertEquals($creationDate, $model->getCreatedAt());
        self::assertEquals($modificationDate, $model->getUpdatedAt());
    }

    public function testCreationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class]);

        $model = new Subscription();
        /** @var Subscriber $subscriber */
        $subscriber = $this->subscriberRepository->find(1);
        $model->setSubscriber($subscriber);
        /** @var SubscriberList $subscriberList */
        $subscriberList = $this->subscriberListRepository->find(1);
        $model->setSubscriberList($subscriberList);
        $expectedCreationDate = new DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedCreationDate, $model->getCreatedAt());
    }

    public function testModificationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class]);

        $model = new Subscription();
        /** @var Subscriber $subscriber */
        $subscriber = $this->subscriberRepository->find(1);
        $model->setSubscriber($subscriber);
        /** @var SubscriberList $subscriberList */
        $subscriberList = $this->subscriberListRepository->find(1);
        $model->setSubscriberList($subscriberList);
        $expectedModificationDate = new DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedModificationDate, $model->getUpdatedAt());
    }

    public function testFindBySubscriberFindsSubscriptionOnlyWithTheGivenSubscriber()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        /** @var Subscriber $subscriber */
        $subscriber = $this->subscriberRepository->find(1);

        $result = $this->subscriptionRepository->findBySubscriber($subscriber);

        /** @var Subscription $subscription */
        foreach ($result as $subscription) {
            self::assertSame($subscriber, $subscription->getSubscriber());
        }
    }

    public function testFindBySubscriberListFindsSubscriptionOnlyWithTheGivenSubscriberList()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        /** @var SubscriberList $subscriberList */
        $subscriberList = $this->subscriberListRepository->find(1);

        $result = $this->subscriptionRepository->findBySubscriberList($subscriberList);

        /** @var Subscription $subscription */
        foreach ($result as $subscription) {
            self::assertSame($subscriberList, $subscription->getSubscriberList());
        }
    }

    public function testSavePersistsAndFlushesModel()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class]);

        $numberOfSaveModelsBefore = count($this->subscriptionRepository->findAll());

        $model = new Subscription();
        /** @var Subscriber $subscriber */
        $subscriber = $this->subscriberRepository->find(1);
        $model->setSubscriber($subscriber);
        /** @var SubscriberList $subscriber */
        $subscriberList = $this->subscriberListRepository->find(1);
        $model->setSubscriberList($subscriberList);
        $this->subscriptionRepository->save($model);

        self::assertCount($numberOfSaveModelsBefore + 1, $this->subscriptionRepository->findAll());
    }

    public function testRemoveRemovesModel()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        /** @var Subscription[] $allModels */
        $allModels = $this->subscriptionRepository->findAll();
        $numberOfModelsBeforeRemove = count($allModels);
        $firstModel = $allModels[0];

        $this->subscriptionRepository->remove($firstModel);

        $numberOfModelsAfterRemove = count($this->subscriptionRepository->findAll());
        self::assertSame(1, $numberOfModelsBeforeRemove - $numberOfModelsAfterRemove);
    }

    public function testFindOneBySubscriberListAndSubscriberForNeitherMatchingReturnsNull()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        $subscriberList = $this->subscriberListRepository->find(3);
        $subscriber = $this->subscriberRepository->find(4);
        $result = $this->subscriptionRepository->findOneBySubscriberListAndSubscriber($subscriberList, $subscriber);

        self::assertNull($result);
    }

    public function testFindOneBySubscriberListAndSubscriberForMatchingSubscriberListOnlyReturnsNull()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        $subscriberList = $this->subscriberListRepository->find(2);
        $subscriber = $this->subscriberRepository->find(4);
        $result = $this->subscriptionRepository->findOneBySubscriberListAndSubscriber($subscriberList, $subscriber);

        self::assertNull($result);
    }

    public function testFindOneBySubscriberListAndSubscriberForMatchingSubscriberOnlyReturnsNull()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        $subscriberList = $this->subscriberListRepository->find(3);
        $subscriber = $this->subscriberRepository->find(1);
        $result = $this->subscriptionRepository->findOneBySubscriberListAndSubscriber($subscriberList, $subscriber);

        self::assertNull($result);
    }

    public function testFindOneBySubscriberListAndSubscriberForBothMatchingReturnsMatch()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        $subscriberList = $this->subscriberListRepository->find(2);
        $subscriber = $this->subscriberRepository->find(1);
        $result = $this->subscriptionRepository->findOneBySubscriberListAndSubscriber($subscriberList, $subscriber);

        self::assertInstanceOf(Subscription::class, $result);
        self::assertSame($subscriberList, $result->getSubscriberList());
        self::assertSame($subscriber, $result->getSubscriber());
    }

    public function testFindOneByListIdAndSubscriberEmailForNeitherMatchingReturnsNull()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        $result = $this->subscriptionRepository->findOneBySubscriberEmailAndListId(3, 'some@random.mail');

        self::assertNull($result);
    }

    public function testFindOneByListIdAndSubscriberEmailForBothMatchingReturnsMatch()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        $subscriberList = $this->subscriberListRepository->find(2);
        $subscriber = $this->subscriberRepository->find(1);
        $result = $this->subscriptionRepository->findOneBySubscriberEmailAndListId(2, $subscriber->getEmail());

        self::assertInstanceOf(Subscription::class, $result);
        self::assertSame($subscriberList, $result->getSubscriberList());
        self::assertSame($subscriber, $result->getSubscriber());
    }
}
