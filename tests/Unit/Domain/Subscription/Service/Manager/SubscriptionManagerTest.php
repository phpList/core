<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service\Manager;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Model\Subscription;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriptionRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriptionManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SubscriptionManagerTest extends TestCase
{
    private SubscriptionRepository&MockObject $subscriptionRepository;
    private SubscriberRepository&MockObject $subscriberRepository;
    private TranslatorInterface&MockObject $translator;
    private EntityManagerInterface&MockObject $entityManager;
    private SubscriptionManager $manager;

    protected function setUp(): void
    {
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $subscriberListRepository = $this->createMock(SubscriberListRepository::class);
        $this->translator = $this->createMock(TranslatorInterface::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->manager = new SubscriptionManager(
            subscriptionRepository: $this->subscriptionRepository,
            subscriberRepository: $this->subscriberRepository,
            subscriberListRepository: $subscriberListRepository,
            translator: $this->translator,
            entityManager: $this->entityManager,
        );
    }

    public function testCreateSubscriptionWhenSubscriberExists(): void
    {
        $email = 'test@example.com';
        $subscriber = new Subscriber($email);
        $list = new SubscriberList();

        $this->subscriberRepository->method('findOneByEmail')->with($email)->willReturn($subscriber);
        $this->subscriptionRepository->method('findOneBySubscriberListAndSubscriber')->willReturn(null);
        $this->entityManager->expects($this->once())->method('persist');

        $subscriptions = $this->manager->createSubscriptions($list, [$email], false);

        $this->assertCount(1, $subscriptions);
        $this->assertInstanceOf(Subscription::class, $subscriptions[0]);
    }

    public function testCreateSubscriptionThrowsWhenSubscriberMissing(): void
    {
        $this->translator->method('trans')->willReturn('Subscriber does not exists.');
        $this->entityManager->expects($this->exactly(2))->method('persist');

        $list = new SubscriberList();

        $this->subscriberRepository->method('findOneByEmail')->willReturn(null);

        $this->manager->createSubscriptions($list, ['missing@example.com'], false);
    }

    public function testDeleteSubscriptionSuccessfully(): void
    {
        $email = 'user@example.com';
        $subscriberList = $this->createMock(SubscriberList::class);
        $subscriberList->method('getId')->willReturn(1);
        $subscription = new Subscription();

        $this->subscriptionRepository
            ->method('findOneBySubscriberEmailAndListId')
            ->with($subscriberList->getId(), $email)
            ->willReturn($subscription);

        $this->entityManager->expects($this->once())->method('remove')->with($subscription);

        $this->manager->deleteSubscriptions($subscriberList, [$email]);
    }

    public function testDeleteSubscriptionSkipsNotFound(): void
    {
        $email = 'missing@example.com';
        $subscriberList = $this->createMock(SubscriberList::class);
        $subscriberList->method('getId')->willReturn(1);

        $this->subscriptionRepository
            ->method('findOneBySubscriberEmailAndListId')
            ->willReturn(null);

        $this->manager->deleteSubscriptions($subscriberList, [$email]);

        $this->addToAssertionCount(1);
    }

    public function testGetSubscriberListMembersReturnsList(): void
    {
        $subscriberList = $this->createMock(SubscriberList::class);
        $subscriberList->method('getId')->willReturn(1);
        $subscriber = new Subscriber('user@example.com');

        $this->subscriberRepository
            ->method('getSubscribersBySubscribedListId')
            ->with($subscriberList->getId())
            ->willReturn([$subscriber]);

        $result = $this->manager->getSubscriberListMembers($subscriberList);

        $this->assertCount(1, $result);
        $this->assertInstanceOf(Subscriber::class, $result[0]);
    }
}
