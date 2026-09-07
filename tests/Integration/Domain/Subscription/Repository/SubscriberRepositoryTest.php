<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Domain\Subscription\Repository;

use DateTime;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Model\Subscription;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriptionRepository;
use PhpList\Core\TestingSupport\Traits\DatabaseTestTrait;
use PhpList\Core\TestingSupport\Traits\SimilarDatesAssertionTrait;
use PhpList\Core\Tests\Integration\Domain\Identity\Fixtures\AdministratorFixture;
use PhpList\Core\Tests\Integration\Domain\Subscription\Fixtures\SubscriberFixture;
use PhpList\Core\Tests\Integration\Domain\Subscription\Fixtures\SubscriberListFixture;
use PhpList\Core\Tests\Integration\Domain\Subscription\Fixtures\SubscriptionFixture;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class SubscriberRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;
    use SimilarDatesAssertionTrait;

    private ?SubscriberRepository $subscriberRepository = null;
    private ?SubscriptionRepository $subscriptionRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

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
        $this->loadFixtures([SubscriberFixture::class]);

        $id = 1;
        $creationDate = new DateTime('2016-07-22 15:01:17');
        $modificationDate = new DateTime('2016-08-23 19:50:43');
        $extraData = 'This is one of our favourite subscribers.';

        /** @var Subscriber $model */
        $model = $this->subscriberRepository->find($id);

        self::assertSame($id, $model->getId());
        self::assertSimilarDates($creationDate, $model->getCreatedAt());
        self::assertSimilarDates($modificationDate, $model->getUpdatedAt());
        self::assertEquals('oliver@example.com', $model->getEmail());
        self::assertTrue($model->isConfirmed());
        self::assertTrue($model->isBlacklisted());
        self::assertSame(17, $model->getBounceCount());
        self::assertSame('95feb7fe7e06e6c11ca8d0c48cb46e89', $model->getUniqueId());
        self::assertTrue($model->hasHtmlEmail());
        self::assertTrue($model->isDisabled());
        self::assertSame($extraData, $model->getExtraData());
    }

    public function testCreationDateOfNewModelIsSetToNowOnPersist()
    {
        $model = new Subscriber('sam@example.com');
        $expectedCreationDate = new DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedCreationDate, $model->getCreatedAt());
    }

    public function testModificationDateOfNewModelIsSetToNowOnPersist()
    {
        $model = new Subscriber('oliver@example.com');
        $expectedModificationDate = new DateTime();

        $this->entityManager->persist($model);

        self::assertSimilarDates($expectedModificationDate, $model->getUpdatedAt());
    }

    public function testSavePersistsAndFlushesModel()
    {
        $model = new Subscriber('michiel@example.com');
        $this->subscriberRepository->save($model);

        self::assertSame($model, $this->subscriberRepository->find($model->getId()));
    }

    public function testEmailMustBeUnique()
    {
        $this->loadFixtures([SubscriberFixture::class]);

        /** @var Subscriber $model */
        $model = $this->subscriberRepository->find(1);

        $otherModel = new Subscriber($model->getEmail());
        $otherModel->generateUniqueId();

        $this->expectException(UniqueConstraintViolationException::class);

        $this->subscriberRepository->save($otherModel);
    }

    public function testUniqueIdOfNewModelIsGeneratedOnPersist()
    {
        $model = new Subscriber('oliver@example.com');
        $this->entityManager->persist($model);

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $model->getUniqueId());
    }

    /**
     * @test
     */
    public function persistingExistingModelKeepsUniqueIdUnchanged()
    {
        $this->loadFixtures([SubscriberFixture::class]);

        /** @var Subscriber $model */
        $model = $this->subscriberRepository->find(1);
        $oldUniqueId = $model->getUniqueId();

        $model->setEmail('other@example.com');
        $this->entityManager->persist($model);

        self::assertSame($oldUniqueId, $model->getUniqueId());
    }

    public function testFindOneByEmailFindsSubscriberWithMatchingEmail()
    {
        $email = 'oliver@example.com';

        $this->loadFixtures([SubscriberFixture::class]);

        /** @var Subscriber $model */
        $model = $this->subscriberRepository->findOneByEmail($email);

        self::assertInstanceOf(Subscriber::class, $model);
        self::assertSame($email, $model->getEmail());
    }

    public function testFindOneByEmailIgnoresSubscriberWithNonMatchingEmail()
    {
        $email = 'other@example.com';

        $this->loadFixtures([SubscriberFixture::class]);

        $model = $this->subscriberRepository->findOneByEmail($email);

        self::assertNull($model);
    }

    public function testFindsAssociatedSubscriptions()
    {
        $this->loadFixtures([
            AdministratorFixture::class,
            SubscriberFixture::class,
            SubscriberListFixture::class,
            SubscriptionFixture::class,
        ]);

        $id = 1;
        $model = $this->subscriberRepository->findSubscriberWithSubscriptions($id);
        $subscriptions = $model->getSubscriptions();

        self::assertFalse($subscriptions->isEmpty());
        /** @var Subscription $firstSubscription */
        $firstSubscription = $subscriptions->first();
        self::assertInstanceOf(Subscription::class, $firstSubscription);
        $expectedSubscriberListId = 2;
        self::assertSame($expectedSubscriberListId, $firstSubscription->getSubscriberList()->getId());
    }

    public function testFindsAssociatedSubscribedLists()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        $id = 1;
        /** @var ?Subscriber $model */
        $model = $this->subscriberRepository->findSubscriberWithSubscriptions($id);
        $subscriberListIds = [];
        foreach ($model->getSubscriptions() as $subscription) {
            $subscriberListIds[] = $subscription->getSubscriberList()->getId();
        }

        self::assertContains(2, $subscriberListIds);
        self::assertNotContains(1, $subscriberListIds);
    }

    public function testRemoveAlsoRemovesAssociatedSubscriptions()
    {
        $this->loadFixtures([SubscriberFixture::class, SubscriberListFixture::class, SubscriptionFixture::class]);

        $initialNumberOfSubscriptions = count($this->subscriptionRepository->findAll());

        $id = 2;
        /** @var Subscriber $model */
        $model = $this->subscriberRepository->findSubscriberWithSubscriptions($id);

        $numberOfAssociatedSubscriptions = count($model->getSubscriptions());
        self::assertGreaterThan(0, $numberOfAssociatedSubscriptions);

        $this->subscriberRepository->delete($model);

        $newNumberOfSubscriptions = count($this->subscriptionRepository->findAll());
        $numberOfRemovedSubscriptions = $initialNumberOfSubscriptions - $newNumberOfSubscriptions;
        self::assertSame($numberOfAssociatedSubscriptions, $numberOfRemovedSubscriptions);
    }

    /**
     * @test
     */
    public function testRemoveRemovesModel()
    {
        $this->loadFixtures([SubscriberFixture::class]);

        /** @var Subscriber[] $allModels */
        $allModels = $this->subscriberRepository->findAll();
        $numberOfModelsBeforeRemove = count($allModels);
        $firstModel = $allModels[0];

        $this->subscriberRepository->delete($firstModel);

        $numberOfModelsAfterRemove = count($this->subscriberRepository->findAll());
        self::assertSame(1, $numberOfModelsBeforeRemove - $numberOfModelsAfterRemove);
    }

    private function subscribe(Subscriber $subscriber, SubscriberList $list): void
    {
        $subscription = (new Subscription())
            ->setSubscriber($subscriber)
            ->setSubscriberList($list);
        $this->entityManager->persist($subscription);
    }

    public function testGetSendableSubscribersBySubscribedListIdExcludesUnconfirmedAndDisabled(): void
    {
        $list = (new SubscriberList())->setName('list');
        $this->entityManager->persist($list);

        $confirmed = (new Subscriber('confirmed@example.com'))->setConfirmed(true);
        $unconfirmed = (new Subscriber('unconfirmed@example.com'))->setConfirmed(false);
        $disabled = (new Subscriber('disabled@example.com'))->setConfirmed(true)->setDisabled(true);
        foreach ([$confirmed, $unconfirmed, $disabled] as $subscriber) {
            $this->entityManager->persist($subscriber);
            $this->subscribe($subscriber, $list);
        }
        $this->entityManager->flush();

        $result = $this->subscriberRepository->getSendableSubscribersBySubscribedListId($list->getId());

        self::assertTrue(in_array($confirmed, $result, true));
        self::assertFalse(in_array($unconfirmed, $result, true));
        self::assertFalse(in_array($disabled, $result, true));
    }

    public function testGetSubscribersBySubscribedListIdsReturnsMembersOfAnyGivenList(): void
    {
        $listA = (new SubscriberList())->setName('a');
        $listB = (new SubscriberList())->setName('b');
        $listC = (new SubscriberList())->setName('c');
        $this->entityManager->persist($listA);
        $this->entityManager->persist($listB);
        $this->entityManager->persist($listC);

        $inA = new Subscriber('in-a@example.com');
        $inB = new Subscriber('in-b@example.com');
        $inC = new Subscriber('in-c@example.com');
        $this->entityManager->persist($inA);
        $this->entityManager->persist($inB);
        $this->entityManager->persist($inC);
        $this->subscribe($inA, $listA);
        $this->subscribe($inB, $listB);
        $this->subscribe($inC, $listC);
        $this->entityManager->flush();

        $result = $this->subscriberRepository->getSubscribersBySubscribedListIds([$listA->getId(), $listB->getId()]);

        self::assertTrue(in_array($inA, $result, true));
        self::assertTrue(in_array($inB, $result, true));
        self::assertFalse(in_array($inC, $result, true));
    }

    public function testGetSubscribersBySubscribedListIdsReturnsEmptyArrayForEmptyInput(): void
    {
        self::assertSame([], $this->subscriberRepository->getSubscribersBySubscribedListIds([]));
    }
}
