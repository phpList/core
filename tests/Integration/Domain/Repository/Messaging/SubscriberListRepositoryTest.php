<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Repository\Messaging;

use DateTime;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\Proxy;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Messaging\SubscriberList;
use PhpList\Core\Domain\Model\Subscription\Subscription;
use PhpList\Core\Domain\Repository\Identity\AdministratorRepository;
use PhpList\Core\Domain\Repository\Messaging\SubscriberListRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriberRepository;
use PhpList\Core\Domain\Repository\Subscription\SubscriptionRepository;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\AdministratorFixture;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\SubscriberFixture;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\SubscriberListFixture;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\SubscriptionFixture;
use PhpList\Core\Tests\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\Tests\TestingSupport\Traits\SimilarDatesAssertionTrait;
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

    private ?AdministratorRepository $administratorRepository = null;
    private ?SubscriberRepository $subscriberRepository = null;
    private ?SubscriptionRepository $subscriptionRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->subject = self::getContainer()->get(SubscriberListRepository::class);
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
        $creationDate = new DateTime('2016-06-22 15:01:17');
        $modificationDate = new DateTime();
        $name = 'News';
        $description = 'News (and some fun stuff)';
        $listPosition = 12;
        $subjectPrefix = 'phpList';
        $category = 'news';

        /** @var SubscriberList $model */
        $model = $this->subject->find($id);

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

    public function testCreatesOwnerAssociationAsProxy()
    {
        $this->loadFixtures([SubscriberListFixture::class, AdministratorFixture::class]);

        $subscriberListId = 1;
        $ownerId = 1;
        /** @var SubscriberList $model */
        $model = $this->subject->find($subscriberListId);
        $owner = $model->getOwner();

        self::assertInstanceOf(Administrator::class, $owner);
//        self::assertInstanceOf(Proxy::class, $owner); todo: check proxy
        self::assertSame($ownerId, $owner->getId());
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
        $this->subject->save($model);

        self::assertSame($model, $this->subject->find($model->getId()));
    }

    public function testFindByOwnerFindsSubscriberListWithTheGivenOwner()
    {
        $this->loadFixtures([SubscriberListFixture::class, AdministratorFixture::class]);

        $owner = $this->administratorRepository->find(1);
        $ownedList = $this->subject->find(1);

        $result = $this->subject->findByOwner($owner);

        self::assertContains($ownedList, $result);
    }

    public function testFindByOwnerIgnoresSubscriberListWithOtherOwner()
    {
        $this->loadFixtures([SubscriberListFixture::class, AdministratorFixture::class]);

        $owner = $this->administratorRepository->find(1);
        $foreignList = $this->subject->find(2);

        $result = $this->subject->findByOwner($owner);

        self::assertNotContains($foreignList, $result);
    }

    public function testFindByOwnerIgnoresSubscriberListFromOtherOwner()
    {
        $this->loadFixtures([SubscriberListFixture::class, AdministratorFixture::class]);

        $owner = $this->administratorRepository->find(1);
        $unownedList = $this->subject->find(3);

        $result = $this->subject->findByOwner($owner);

        self::assertNotContains($unownedList, $result);
    }

    public function testFindsAssociatedSubscriptions()
    {
        $this->loadFixtures([SubscriberListFixture::class, SubscriberFixture::class, SubscriptionFixture::class]);

        $id = 2;
        /** @var SubscriberList $model */
        $model = $this->subject->find($id);
        $subscriptions = $model->getSubscriptions();

        self::assertFalse($subscriptions->isEmpty());
        /** @var Subscription $firstSubscription */
        $firstSubscription = $subscriptions->first();
        self::assertInstanceOf(Subscription::class, $firstSubscription);
        $expectedSubscriberId = 1;
        self::assertSame($expectedSubscriberId, $firstSubscription->getSubscriber()->getId());
    }

    public function testFindsAssociatedSubscribers()
    {
        $this->loadFixtures([SubscriberListFixture::class, SubscriberFixture::class, SubscriptionFixture::class]);

        $id = 2;
        /** @var SubscriberList $model */
        $model = $this->subject->find($id);
        $subscribers = $model->getSubscribers();

        $expectedSubscriber = $this->subscriberRepository->find(1);
        $unexpectedSubscriber = $this->subscriberRepository->find(3);
        self::assertTrue($subscribers->contains($expectedSubscriber));
        self::assertFalse($subscribers->contains($unexpectedSubscriber));
    }

    public function testRemoveAlsoRemovesAssociatedSubscriptions()
    {
        $this->loadFixtures([SubscriberListFixture::class, SubscriberFixture::class, SubscriptionFixture::class]);

        $initialNumberOfSubscriptions = count($this->subscriptionRepository->findAll());

        $id = 2;
        /** @var SubscriberList $model */
        $model = $this->subject->find($id);

        $numberOfAssociatedSubscriptions = count($model->getSubscriptions());
        self::assertGreaterThan(0, $numberOfAssociatedSubscriptions);

        $this->subject->remove($model);

        $newNumberOfSubscriptions = count($this->subscriptionRepository->findAll());
        $numberOfRemovedSubscriptions = $initialNumberOfSubscriptions - $newNumberOfSubscriptions;
        self::assertSame($numberOfAssociatedSubscriptions, $numberOfRemovedSubscriptions);
    }

    public function testRemoveRemovesModel()
    {
        $this->loadFixtures([SubscriberListFixture::class]);

        /** @var SubscriberList[] $allModels */
        $allModels = $this->subject->findAll();
        $numberOfModelsBeforeRemove = count($allModels);
        $firstModel = $allModels[0];

        $this->subject->remove($firstModel);

        $numberOfModelsAfterRemove = count($this->subject->findAll());
        self::assertSame(1, $numberOfModelsBeforeRemove - $numberOfModelsAfterRemove);
    }
}
