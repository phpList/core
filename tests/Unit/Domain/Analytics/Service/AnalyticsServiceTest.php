<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Analytics\Service;

use DateTime;
use PhpList\Core\Domain\Analytics\Model\LinkTrack;
use PhpList\Core\Domain\Analytics\Service\AnalyticsService;
use PhpList\Core\Domain\Analytics\Service\Manager\LinkTrackManager;
use PhpList\Core\Domain\Analytics\Service\Manager\UserMessageViewManager;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageBounceRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AnalyticsServiceTest extends TestCase
{
    private AnalyticsService $subject;
    private LinkTrackManager|MockObject $linkTrackManager;
    private UserMessageViewManager|MockObject $userMessageViewManager;
    private MessageRepository|MockObject $messageRepository;
    private UserMessageBounceRepository|MockObject $userMessageBounceRepository;
    private UserMessageForwardRepository|MockObject $userMessageForwardRepository;
    private SubscriberRepository|MockObject $subscriberRepository;

    protected function setUp(): void
    {
        $this->linkTrackManager = $this->createMock(LinkTrackManager::class);
        $this->userMessageViewManager = $this->createMock(UserMessageViewManager::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->userMessageBounceRepository = $this->createMock(UserMessageBounceRepository::class);
        $this->userMessageForwardRepository = $this->createMock(UserMessageForwardRepository::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);

        $this->subject = new AnalyticsService(
            $this->linkTrackManager,
            $this->userMessageViewManager,
            $this->messageRepository,
            $this->userMessageBounceRepository,
            $this->userMessageForwardRepository,
            $this->subscriberRepository
        );
    }

    public function testGetCampaignStatistics(): void
    {
        $limit = 50;
        $lastId = 0;
        $messageId = 123;

        $messageMetadata = $this->createMock(MessageMetadata::class);
        $messageMetadata->method('getSent')->willReturn(new DateTime('2023-01-01 10:00:00'));
        $messageMetadata->method('getBounceCount')->willReturn(5);

        $messageContent = $this->createMock(MessageContent::class);
        $messageContent->method('getSubject')->willReturn('Test Campaign');

        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn($messageId);
        $message->method('getMetadata')->willReturn($messageMetadata);
        $message->method('getContent')->willReturn($messageContent);

        $linkTrack1 = new LinkTrack();
        $linkTrack1->setUserId(1);
        $linkTrack1->setClicked(2);

        $linkTrack2 = new LinkTrack();
        $linkTrack2->setUserId(2);
        $linkTrack2->setClicked(3);

        $this->messageRepository->expects(self::once())
            ->method('getFilteredAfterId')
            ->with($lastId, $limit)
            ->willReturn([$message]);

        $this->userMessageViewManager->expects(self::once())
            ->method('countViewsByMessageId')
            ->with($messageId)
            ->willReturn(10);

        $this->linkTrackManager->expects(self::once())
            ->method('getLinkTracksByMessageId')
            ->with($messageId)
            ->willReturn([$linkTrack1, $linkTrack2]);

        $this->userMessageBounceRepository->expects(self::once())
            ->method('getCountByMessageId')
            ->with($messageId)
            ->willReturn(3);

        $this->userMessageForwardRepository->expects(self::once())
            ->method('getCountByMessageId')
            ->with($messageId)
            ->willReturn(2);

        $result = $this->subject->getCampaignStatistics($limit, $lastId);

        self::assertArrayHasKey('campaigns', $result);
        self::assertCount(1, $result['campaigns']);
        self::assertSame(1, $result['total']);
        self::assertFalse($result['hasMore']);
        self::assertSame($messageId, $result['lastId']);

        $campaign = $result['campaigns'][0];
        self::assertSame($messageId, $campaign['campaignId']);
        self::assertSame('Test Campaign', $campaign['subject']);
        self::assertSame('2023-01-01 10:00:00', $campaign['dateSent']);
        self::assertSame(15, $campaign['sent']);
        self::assertSame(3, $campaign['bounces']);
        self::assertSame(2, $campaign['forwards']);
        self::assertSame(10, $campaign['uniqueViews']);
        self::assertSame(5, $campaign['totalClicks']);
        self::assertSame(2, $campaign['uniqueClicks']);
    }

    public function testGetViewOpensStatistics(): void
    {
        $limit = 50;
        $lastId = 0;
        $messageId = 123;

        $messageMetadata = $this->createMock(MessageMetadata::class);
        $messageMetadata->method('getBounceCount')->willReturn(5);

        $messageContent = $this->createMock(MessageContent::class);
        $messageContent->method('getSubject')->willReturn('Test Campaign');

        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn($messageId);
        $message->method('getMetadata')->willReturn($messageMetadata);
        $message->method('getContent')->willReturn($messageContent);

        $this->messageRepository->expects(self::once())
            ->method('getFilteredAfterId')
            ->with($lastId, $limit)
            ->willReturn([$message]);

        $this->userMessageViewManager->expects(self::once())
            ->method('countViewsByMessageId')
            ->with($messageId)
            ->willReturn(10);

        $result = $this->subject->getViewOpensStatistics($limit, $lastId);

        self::assertArrayHasKey('campaigns', $result);
        self::assertCount(1, $result['campaigns']);
        self::assertSame(1, $result['total']);
        self::assertFalse($result['hasMore']);
        self::assertSame($messageId, $result['lastId']);

        $campaign = $result['campaigns'][0];
        self::assertSame($messageId, $campaign['campaignId']);
        self::assertSame('Test Campaign', $campaign['subject']);
        self::assertSame(15, $campaign['sent']);
        self::assertSame(10, $campaign['uniqueViews']);
        self::assertSame(66.7, $campaign['rate']);
    }

    public function testGetTopDomains(): void
    {
        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getEmail')->willReturn('user1@example.com');

        $subscriber2 = $this->createMock(Subscriber::class);
        $subscriber2->method('getEmail')->willReturn('user2@example.com');

        $subscriber3 = $this->createMock(Subscriber::class);
        $subscriber3->method('getEmail')->willReturn('user3@example.com');

        $subscriber4 = $this->createMock(Subscriber::class);
        $subscriber4->method('getEmail')->willReturn('user4@example.com');

        $subscriber5 = $this->createMock(Subscriber::class);
        $subscriber5->method('getEmail')->willReturn('user5@example.com');

        $subscriber6 = $this->createMock(Subscriber::class);
        $subscriber6->method('getEmail')->willReturn('user6@example.com');

        $subscriber7 = $this->createMock(Subscriber::class);
        $subscriber7->method('getEmail')->willReturn('user1@test.com');

        $subscriber8 = $this->createMock(Subscriber::class);
        $subscriber8->method('getEmail')->willReturn('user2@test.com');

        $subscriber9 = $this->createMock(Subscriber::class);
        $subscriber9->method('getEmail')->willReturn('user3@another.com');

        $this->subscriberRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([
                $subscriber1, $subscriber2, $subscriber3, $subscriber4, $subscriber5,
                $subscriber6, $subscriber7, $subscriber8, $subscriber9
            ]);

        $result = $this->subject->getTopDomains(50, 1);

        self::assertArrayHasKey('domains', $result);
        self::assertArrayHasKey('total', $result);

        self::assertSame(3, $result['total']);

        self::assertSame('example.com', $result['domains'][0]['domain']);
        self::assertSame(6, $result['domains'][0]['subscribers']);

        self::assertSame('test.com', $result['domains'][1]['domain']);
        self::assertSame(2, $result['domains'][1]['subscribers']);

        self::assertSame('another.com', $result['domains'][2]['domain']);
        self::assertSame(1, $result['domains'][2]['subscribers']);
    }

    public function testGetDomainConfirmationStatistics(): void
    {
        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getEmail')->willReturn('user1@example.com');
        $subscriber1->method('isConfirmed')->willReturn(true);
        $subscriber1->method('isBlacklisted')->willReturn(false);

        $subscriber2 = $this->createMock(Subscriber::class);
        $subscriber2->method('getEmail')->willReturn('user2@example.com');
        $subscriber2->method('isConfirmed')->willReturn(true);
        $subscriber2->method('isBlacklisted')->willReturn(false);

        $subscriber3 = $this->createMock(Subscriber::class);
        $subscriber3->method('getEmail')->willReturn('user3@example.com');
        $subscriber3->method('isConfirmed')->willReturn(false);
        $subscriber3->method('isBlacklisted')->willReturn(false);

        $subscriber4 = $this->createMock(Subscriber::class);
        $subscriber4->method('getEmail')->willReturn('user4@example.com');
        $subscriber4->method('isConfirmed')->willReturn(false);
        $subscriber4->method('isBlacklisted')->willReturn(false);

        $subscriber5 = $this->createMock(Subscriber::class);
        $subscriber5->method('getEmail')->willReturn('user5@example.com');
        $subscriber5->method('isConfirmed')->willReturn(false);
        $subscriber5->method('isBlacklisted')->willReturn(true);

        $subscriber6 = $this->createMock(Subscriber::class);
        $subscriber6->method('getEmail')->willReturn('user1@test.com');
        $subscriber6->method('isConfirmed')->willReturn(true);
        $subscriber6->method('isBlacklisted')->willReturn(false);

        $subscriber7 = $this->createMock(Subscriber::class);
        $subscriber7->method('getEmail')->willReturn('user2@test.com');
        $subscriber7->method('isConfirmed')->willReturn(false);
        $subscriber7->method('isBlacklisted')->willReturn(false);

        $this->subscriberRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([
                $subscriber1, $subscriber2, $subscriber3, $subscriber4,
                $subscriber5, $subscriber6, $subscriber7
            ]);

        $result = $this->subject->getDomainConfirmationStatistics();

        self::assertArrayHasKey('domains', $result);
        self::assertArrayHasKey('total', $result);

        self::assertSame(2, $result['total']);

        $exampleDomain = $result['domains'][0];
        self::assertSame('example.com', $exampleDomain['domain']);
        self::assertSame(2, $exampleDomain['confirmed']['count']);
        self::assertSame(40, $exampleDomain['confirmed']['percentage']);
        self::assertSame(2, $exampleDomain['unconfirmed']['count']);
        self::assertSame(40, $exampleDomain['unconfirmed']['percentage']);
        self::assertSame(1, $exampleDomain['blacklisted']['count']);
        self::assertSame(20, $exampleDomain['blacklisted']['percentage']);
        self::assertSame(5, $exampleDomain['total']['count']);

        $testDomain = $result['domains'][1];
        self::assertSame('test.com', $testDomain['domain']);
        self::assertSame(1, $testDomain['confirmed']['count']);
        self::assertSame(50, $testDomain['confirmed']['percentage']);
        self::assertSame(1, $testDomain['unconfirmed']['count']);
        self::assertSame(50, $testDomain['unconfirmed']['percentage']);
        self::assertSame(0, $testDomain['blacklisted']['count']);
        self::assertSame(0, $testDomain['blacklisted']['percentage']);
        self::assertSame(2, $testDomain['total']['count']);
    }

    public function testGetTopLocalParts(): void
    {
        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber1->method('getEmail')->willReturn('user1@example.com');

        $subscriber2 = $this->createMock(Subscriber::class);
        $subscriber2->method('getEmail')->willReturn('user2@example.com');

        $subscriber3 = $this->createMock(Subscriber::class);
        $subscriber3->method('getEmail')->willReturn('user1@test.com');

        $subscriber4 = $this->createMock(Subscriber::class);
        $subscriber4->method('getEmail')->willReturn('admin@example.com');

        $subscriber5 = $this->createMock(Subscriber::class);
        $subscriber5->method('getEmail')->willReturn('info@example.com');

        $this->subscriberRepository->expects(self::once())
            ->method('findAll')
            ->willReturn([
                $subscriber1, $subscriber2, $subscriber3, $subscriber4, $subscriber5
            ]);

        $result = $this->subject->getTopLocalParts();

        self::assertArrayHasKey('localParts', $result);
        self::assertArrayHasKey('total', $result);

        self::assertSame(4, $result['total']);

        self::assertSame('user1', $result['localParts'][0]['localPart']);
        self::assertSame(2, $result['localParts'][0]['count']);
        self::assertSame(40, $result['localParts'][0]['percentage']);

        self::assertSame(1, $result['localParts'][1]['count']);
        self::assertSame(20, $result['localParts'][1]['percentage']);
    }
}
