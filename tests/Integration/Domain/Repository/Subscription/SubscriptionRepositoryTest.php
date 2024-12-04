<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Subscription;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Model\Messaging\SubscriberList;
use PhpList\Core\Domain\Model\Subscription\Subscriber;
use PhpList\Core\Domain\Model\Subscription\Subscription;
use PhpList\Core\Domain\Repository\Messaging\SubscriberListRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriberRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriptionRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\SubscriberFixture;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\SubscriberListFixture;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\SubscriptionFixture;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

//use Doctrine\ORM\Proxy\Proxy;

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
        $this->loadFixtures([SubscriptionFixture::class]);

        $creationDate = new DateTime('2016-07-22 15:01:17');
        $modificationDate = new DateTime('2016-08-23 19:50:43');

        /** @var Subscription[] $result */
        $result = $this->subscriptionRepository->findAll();

        self::assertNotEmpty($result);

        $model = $result[0];
        self::assertInstanceOf(Subscription::class, $model);
        self::assertEquals($creationDate, $model->getCreationDate());
        self::assertEquals($modificationDate, $model->getModificationDate());
    }

    public function testCreatesSubscriberAssociationAsProxy()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriptionFixture::class]);

        $subscriberId = 1;
        /** @var Subscription $model */
        $model = $this->subscriptionRepository->findAll()[0];
        $subscriber = $model->getSubscriber();

        self::assertInstanceOf(Subscriber::class, $subscriber);
//        self::assertInstanceOf(Proxy::class, $subscriber); // todo: check proxy
        self::assertSame($subscriberId, $subscriber->getId());
    }

    public function testCreatesSubscriberListAssociationAsProxy()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriptionFixture::class]);

        $subscriberListId = 2;
        /** @var Subscription $model */
        $model = $this->subscriberRepository->findAll()[0];
        $subscriberList = $model->getSubscriberList();

        self::assertInstanceOf(SubscriberList::class, $subscriberList);
        self::assertInstanceOf(Proxy::class, $subscriberList);
        self::assertSame($subscriberListId, $subscriberList->getId());
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

        self::assertSimilarDates($expectedCreationDate, $model->getCreationDate());
    }

    public function testModificationDateOfNewModelIsSetToNowOnPersist()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberLIstFixture::class]);

        $model = new Subscription();
        /** @var Subscriber $subscriber */
        $subscriber = $this->subscriberRepository->find(1);
        $model->setSubscriber($subscriber);
        /** @var SubscriberList $subscriberList */
        $subscriberList = $this->subscriberListRepository->find(1);
        $model->setSubscriberList($subscriberList);
        $expectedModificationDate = new DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedModificationDate, $model->getModificationDate());
    }

    public function testFindBySubscriberFindsSubscriptionOnlyWithTheGivenSubscriber()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriptionFixture::class]);

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
        $this->loadFixtures([SubscriberFixture::class, SubscriptionFixture::class]);

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
        $this->loadFixtures([SubscriptionFixture::class]);

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
}
