<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use PhpList\Core\Domain\Subscription\Exception\SubscriptionCreationException;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberList;
use PhpList\Core\Domain\Subscription\Model\Subscription;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriptionRepository;
use PhpList\Core\Domain\Subscription\Service\SubscriptionManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriptionManagerTest extends TestCase
{
    private SubscriptionRepository&MockObject $subscriptionRepository;
    private SubscriberRepository&MockObject $subscriberRepository;
    private SubscriptionManager $manager;

    protected function setUp(): void
    {
        $this->subscriptionRepository = $this->createMock(SubscriptionRepository::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $subscriberListRepository = $this->createMock(SubscriberListRepository::class);
        $this->manager = new SubscriptionManager($this->subscriptionRepository, $this->subscriberRepository, $subscriberListRepository);
    }

    public function testCreateSubscriptionWhenSubscriberExists(): void
    {
        $email = 'test@example.com';
        $subscriber = new Subscriber();
        $list = new SubscriberList();

        $this->subscriberRepository->method('findOneBy')->with(['email' => $email])->willReturn($subscriber);
        $this->subscriptionRepository->method('findOneBySubscriberListAndSubscriber')->willReturn(null);
        $this->subscriptionRepository->expects($this->once())->method('save');

        $subscriptions = $this->manager->createSubscriptions($list, [$email]);

        $this->assertCount(1, $subscriptions);
        $this->assertInstanceOf(Subscription::class, $subscriptions[0]);
    }

    public function testCreateSubscriptionThrowsWhenSubscriberMissing(): void
    {
        $this->expectException(SubscriptionCreationException::class);
        $this->expectExceptionMessage('Subscriber does not exists.');

        $list = new SubscriberList();

        $this->subscriberRepository->method('findOneBy')->willReturn(null);

        $this->manager->createSubscriptions($list, ['missing@example.com']);
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

        $this->subscriptionRepository->expects($this->once())->method('remove')->with($subscription);

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
        $subscriber = new Subscriber();

        $this->subscriberRepository
            ->method('getSubscribersBySubscribedListId')
            ->with($subscriberList->getId())
            ->willReturn([$subscriber]);

        $result = $this->manager->getSubscriberListMembers($subscriberList);

        $this->assertIsArray($result);
        $this->assertCount(1, $result);
        $this->assertInstanceOf(Subscriber::class, $result[0]);
    }
}
