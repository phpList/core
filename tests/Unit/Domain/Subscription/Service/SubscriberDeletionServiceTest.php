<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackUmlClickRepository;
use PhpList\Core\Domain\Analytics\Repository\UserMessageViewRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Analytics\Model\LinkTrackUmlClick;
use PhpList\Core\Domain\Analytics\Model\UserMessageView;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Model\UserMessageBounce;
use PhpList\Core\Domain\Messaging\Model\UserMessageForward;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Model\SubscriberAttributeValue;
use PhpList\Core\Domain\Subscription\Model\SubscriberHistory;
use PhpList\Core\Domain\Subscription\Model\Subscription;
use PhpList\Core\Domain\Subscription\Repository\SubscriberAttributeValueRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberHistoryRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\SubscriberDeletionService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SubscriberDeletionServiceTest extends TestCase
{
    private LinkTrackUmlClickRepository&MockObject $linkTrackUmlClickRepository;
    private EntityManagerInterface&MockObject $entityManager;
    private UserMessageRepository&MockObject $userMessageRepository;
    private SubscriberRepository&MockObject $subscriberRepository;
    private SubscriberAttributeValueRepository&MockObject $subscriberAttributeValueRepository;
    private SubscriberHistoryRepository&MockObject $subscriberHistoryRepository;
    private UserMessageBounceRepository&MockObject $userMessageBounceRepository;
    private UserMessageForwardRepository&MockObject $userMessageForwardRepository;
    private UserMessageViewRepository&MockObject $userMessageViewRepository;
    private SubscriberDeletionService $service;

    protected function setUp(): void
    {
        $this->linkTrackUmlClickRepository = $this->createMock(LinkTrackUmlClickRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->userMessageRepository = $this->createMock(UserMessageRepository::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->subscriberAttributeValueRepository = $this->createMock(SubscriberAttributeValueRepository::class);
        $this->subscriberHistoryRepository = $this->createMock(SubscriberHistoryRepository::class);
        $this->userMessageBounceRepository = $this->createMock(UserMessageBounceRepository::class);
        $this->userMessageForwardRepository = $this->createMock(UserMessageForwardRepository::class);
        $this->userMessageViewRepository = $this->createMock(UserMessageViewRepository::class);

        $this->service = new SubscriberDeletionService(
            $this->linkTrackUmlClickRepository,
            $this->entityManager,
            $this->userMessageRepository,
            $this->subscriberRepository,
            $this->subscriberAttributeValueRepository,
            $this->subscriberHistoryRepository,
            $this->userMessageBounceRepository,
            $this->userMessageForwardRepository,
            $this->userMessageViewRepository
        );
    }

    public function testDeleteLeavingBlacklistRemovesAllRelatedData(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $subscriberId = 123;
        $subscriber->method('getId')->willReturn($subscriberId);

        $subscription = $this->createMock(Subscription::class);
        $subscriptions = new ArrayCollection([$subscription]);
        $subscriber->method('getSubscriptions')->willReturn($subscriptions);

        $linkTrackUmlClick = $this->createMock(LinkTrackUmlClick::class);
        $this->linkTrackUmlClickRepository
            ->method('findBy')
            ->with(['userid' => $subscriberId])
            ->willReturn([$linkTrackUmlClick]);
        $this->linkTrackUmlClickRepository
            ->expects($this->once())
            ->method('remove')
            ->with($linkTrackUmlClick);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($subscription);

        $userMessage = $this->createMock(UserMessage::class);
        $this->userMessageRepository
            ->method('findBy')
            ->with(['user' => $subscriber])
            ->willReturn([$userMessage]);
        $this->userMessageRepository
            ->expects($this->once())
            ->method('remove')
            ->with($userMessage);

        $subscriberAttribute = $this->createMock(SubscriberAttributeValue::class);
        $this->subscriberAttributeValueRepository
            ->method('findBy')
            ->with(['subscriber' => $subscriber])
            ->willReturn([$subscriberAttribute]);
        $this->subscriberAttributeValueRepository
            ->expects($this->once())
            ->method('remove')
            ->with($subscriberAttribute);

        $subscriberHistory = $this->createMock(SubscriberHistory::class);
        $this->subscriberHistoryRepository
            ->method('findBy')
            ->with(['subscriber' => $subscriber])
            ->willReturn([$subscriberHistory]);
        $this->subscriberHistoryRepository
            ->expects($this->once())
            ->method('remove')
            ->with($subscriberHistory);

        $userMessageBounce = $this->createMock(UserMessageBounce::class);
        $this->userMessageBounceRepository
            ->method('findBy')
            ->with(['userId' => $subscriberId])
            ->willReturn([$userMessageBounce]);
        $this->userMessageBounceRepository
            ->expects($this->once())
            ->method('remove')
            ->with($userMessageBounce);

        $userMessageForward = $this->createMock(UserMessageForward::class);
        $this->userMessageForwardRepository
            ->method('findBy')
            ->with(['userId' => $subscriberId])
            ->willReturn([$userMessageForward]);
        $this->userMessageForwardRepository
            ->expects($this->once())
            ->method('remove')
            ->with($userMessageForward);

        $userMessageView = $this->createMock(UserMessageView::class);
        $this->userMessageViewRepository
            ->method('findBy')
            ->with(['userid' => $subscriberId])
            ->willReturn([$userMessageView]);
        $this->userMessageViewRepository
            ->expects($this->once())
            ->method('remove')
            ->with($userMessageView);

        $this->subscriberRepository
            ->expects($this->once())
            ->method('remove')
            ->with($subscriber);

        $this->service->deleteLeavingBlacklist($subscriber);
    }

    public function testDeleteLeavingBlacklistHandlesEmptyRelatedData(): void
    {
        $subscriber = $this->createMock(Subscriber::class);
        $subscriberId = 123;
        $subscriber->method('getId')->willReturn($subscriberId);

        $emptySubscriptions = new ArrayCollection();
        $subscriber->method('getSubscriptions')->willReturn($emptySubscriptions);

        $this->linkTrackUmlClickRepository
            ->method('findBy')
            ->with(['userid' => $subscriberId])
            ->willReturn([]);
        $this->linkTrackUmlClickRepository
            ->expects($this->never())
            ->method('remove');

        $this->userMessageRepository
            ->method('findBy')
            ->with(['user' => $subscriber])
            ->willReturn([]);
        $this->userMessageRepository
            ->expects($this->never())
            ->method('remove');

        $this->subscriberAttributeValueRepository
            ->method('findBy')
            ->with(['subscriber' => $subscriber])
            ->willReturn([]);
        $this->subscriberAttributeValueRepository
            ->expects($this->never())
            ->method('remove');

        $this->subscriberHistoryRepository
            ->method('findBy')
            ->with(['subscriber' => $subscriber])
            ->willReturn([]);
        $this->subscriberHistoryRepository
            ->expects($this->never())
            ->method('remove');

        $this->userMessageBounceRepository
            ->method('findBy')
            ->with(['userId' => $subscriberId])
            ->willReturn([]);
        $this->userMessageBounceRepository
            ->expects($this->never())
            ->method('remove');

        $this->userMessageForwardRepository
            ->method('findBy')
            ->with(['userId' => $subscriberId])
            ->willReturn([]);
        $this->userMessageForwardRepository
            ->expects($this->never())
            ->method('remove');

        $this->userMessageViewRepository
            ->method('findBy')
            ->with(['userid' => $subscriberId])
            ->willReturn([]);
        $this->userMessageViewRepository
            ->expects($this->never())
            ->method('remove');

        $this->subscriberRepository
            ->expects($this->once())
            ->method('remove')
            ->with($subscriber);

        $this->service->deleteLeavingBlacklist($subscriber);
    }
}
