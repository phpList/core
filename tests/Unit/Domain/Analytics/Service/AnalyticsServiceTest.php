<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Analytics\Service;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use PhpList\Core\Domain\Analytics\Model\LinkTrack;
use PhpList\Core\Domain\Analytics\Repository\UserMessageViewRepository;
use PhpList\Core\Domain\Analytics\Service\AnalyticsService;
use PhpList\Core\Domain\Analytics\Service\Manager\LinkTrackManager;
use PhpList\Core\Domain\Analytics\Service\Manager\UserMessageViewManager;
use PhpList\Core\Domain\Common\Model\PaginatedResult;
use PhpList\Core\Domain\Messaging\Model\Filter\MessageFilter;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Model\Message\MessageMetadata;
use PhpList\Core\Domain\Messaging\Repository\Interfaces\UserMessageBounceReaderInterface;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageForwardRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AnalyticsServiceTest extends TestCase
{
    private AnalyticsService $subject;
    private LinkTrackManager|MockObject $linkTrackManager;
    private UserMessageViewManager|MockObject $userMessageViewManager;
    private MessageRepository|MockObject $messageRepository;
    private UserMessageBounceReaderInterface|MockObject $userMessageBounceReader;
    private UserMessageForwardRepository|MockObject $userMessageForwardRepository;
    private SubscriberRepository|MockObject $subscriberRepository;
    private UserMessageRepository|MockObject $userMessageRepository;
    private UserMessageViewRepository|MockObject $userMessageViewRepository;

    protected function setUp(): void
    {
        $this->linkTrackManager = $this->createMock(LinkTrackManager::class);
        $this->userMessageViewManager = $this->createMock(UserMessageViewManager::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->userMessageBounceReader = $this->createMock(UserMessageBounceReaderInterface::class);
        $this->userMessageForwardRepository = $this->createMock(UserMessageForwardRepository::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->userMessageRepository = $this->createMock(UserMessageRepository::class);
        $this->userMessageViewRepository = $this->createMock(UserMessageViewRepository::class);

        $this->subject = new AnalyticsService(
            $this->linkTrackManager,
            $this->userMessageViewManager,
            $this->messageRepository,
            $this->userMessageBounceReader,
            $this->userMessageForwardRepository,
            $this->subscriberRepository,
            $this->userMessageRepository,
            $this->userMessageViewRepository
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
        $messageResult = new PaginatedResult([$message], 1, 1, 1);

        $linkTrack1 = new LinkTrack();
        $linkTrack1->setUserId(1);
        $linkTrack1->setClicked(2);

        $linkTrack2 = new LinkTrack();
        $linkTrack2->setUserId(2);
        $linkTrack2->setClicked(3);

        $this->messageRepository->expects(self::once())
            ->method('getFilteredAfterId')
            ->with($this->callback(function (MessageFilter $filter) use ($lastId, $limit): bool {
                return $filter->getLastId() === $lastId && $filter->getLimit() === $limit;
            }))
            ->willReturn($messageResult);

        $this->userMessageViewManager->expects(self::once())
            ->method('countViewsByMessageId')
            ->with($messageId)
            ->willReturn(10);

        $this->userMessageViewManager->expects(self::once())
            ->method('countUniqueViewsByMessageId')
            ->with($messageId)
            ->willReturn(3);

        $this->linkTrackManager->expects(self::once())
            ->method('getLinkTracksByMessageId')
            ->with($messageId)
            ->willReturn([$linkTrack1, $linkTrack2]);

        $this->userMessageBounceReader->expects(self::once())
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
        self::assertSame(3, $campaign['uniqueViews']);
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
        $messageResult = new PaginatedResult([$message], 1, 1, $messageId);

        $this->messageRepository->expects(self::once())
            ->method('getFilteredAfterId')
            ->with($this->callback(function (MessageFilter $filter) use ($lastId, $limit): bool {
                return $filter->getLastId() === $lastId && $filter->getLimit() === $limit;
            }))
            ->willReturn($messageResult);

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
        $this->subscriberRepository->expects(self::once())
            ->method('getTopDomains')
            ->with(50, 1)
            ->willReturn([
                ['domain' => 'example.com', 'subscribers' => 6],
                ['domain' => 'test.com', 'subscribers' => 2],
                ['domain' => 'another.com', 'subscribers' => 1],
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
        $this->subscriberRepository->expects(self::once())
            ->method('getDomainConfirmationStatistics')
            ->with(50)
            ->willReturn([
                ['domain' => 'example.com', 'total' => 5, 'confirmed' => 2, 'unconfirmed' => 2, 'blacklisted' => 1],
                ['domain' => 'test.com', 'total' => 2, 'confirmed' => 1, 'unconfirmed' => 1, 'blacklisted' => 0],
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
        $this->subscriberRepository->expects(self::once())
            ->method('getTopLocalParts')
            ->with(25)
            ->willReturn([
                ['localPart' => 'user1', 'count' => 2],
                ['localPart' => 'user2', 'count' => 1],
                ['localPart' => 'admin', 'count' => 1],
                ['localPart' => 'info', 'count' => 1],
            ]);

        $this->subscriberRepository->expects(self::once())
            ->method('countWithValidEmail')
            ->willReturn(5);

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

    public function testGetSummaryStatistics(): void
    {
        $this->subscriberRepository->method('count')->willReturn(1000);
        $this->subscriberRepository->method('countCreatedBetween')->willReturnOnConsecutiveCalls(100, 50);

        $this->messageRepository->method('countActiveBetween')->willReturnOnConsecutiveCalls(5, 4);

        $this->userMessageRepository->method('countSentBetween')->willReturnOnConsecutiveCalls(500, 400);
        $this->userMessageViewRepository->method('countBetween')->willReturnOnConsecutiveCalls(250, 160);
        $this->userMessageBounceReader->method('countBetween')->willReturnOnConsecutiveCalls(10, 8);

        $result = $this->subject->getSummaryStatistics();

        self::assertArrayHasKey('total_subscribers', $result);
        self::assertSame(1000, $result['total_subscribers']['value']);
        self::assertEquals(100.0, $result['total_subscribers']['change_vs_last_month']);

        self::assertArrayHasKey('active_campaigns', $result);
        self::assertSame(5, $result['active_campaigns']['value']);
        self::assertEquals(25.0, $result['active_campaigns']['change_vs_last_month']);

        self::assertArrayHasKey('open_rate', $result);
        self::assertEquals(50.0, $result['open_rate']['value']);
        self::assertEquals(25.0, $result['open_rate']['change_vs_last_month']);

        self::assertArrayHasKey('bounce_rate', $result);
        self::assertEquals(2.0, $result['bounce_rate']['value']);
        self::assertEquals(0.0, $result['bounce_rate']['change_vs_last_month']);
    }

    public function testGetCampaignPerformance(): void
    {
        $endDate = new DateTimeImmutable('today 23:59:59');
        $startDate = $endDate->sub(new DateInterval('P29D'))->modify('00:00:00');

        $someDay = $startDate->add(new DateInterval('P5D'))->format('Y-m-d');

        $this->userMessageViewManager->expects(self::once())
            ->method('countViewsGroupedByDay')
            ->with($startDate, $endDate)
            ->willReturn([$someDay => 7]);

        $this->linkTrackManager->expects(self::once())
            ->method('countClicksGroupedByDay')
            ->with($startDate, $endDate)
            ->willReturn([$someDay => 3]);

        $result = $this->subject->getCampaignPerformance();

        self::assertCount(30, $result);

        $matching = array_values(array_filter($result, static fn ($row) => $row['date'] === $someDay));
        self::assertCount(1, $matching);
        self::assertSame(7, $matching[0]['opens']);
        self::assertSame(3, $matching[0]['clicks']);

        $other = array_values(array_filter($result, static fn ($row) => $row['date'] !== $someDay));
        self::assertSame(0, $other[0]['opens']);
        self::assertSame(0, $other[0]['clicks']);
    }

    public function testGetRecentCampaigns(): void
    {
        $limit = 5;
        $messageId = 42;

        $messageMetadata = $this->createMock(MessageMetadata::class);
        $messageMetadata->method('getViews')->willReturn(80);
        $messageMetadata->method('getBounceCount')->willReturn(20);
        $messageMetadata->method('getSent')->willReturn(new DateTime('2023-02-01 10:00:00'));
        $messageMetadata->method('getStatus')->willReturn(null);

        $messageContent = $this->createMock(MessageContent::class);
        $messageContent->method('getSubject')->willReturn('Recent Campaign');

        $message = $this->createMock(Message::class);
        $message->method('getId')->willReturn($messageId);
        $message->method('getMetadata')->willReturn($messageMetadata);
        $message->method('getContent')->willReturn($messageContent);

        $messageResult = new PaginatedResult([$message], 1, 1, $messageId);

        $this->messageRepository->expects(self::once())
            ->method('getFilteredAfterId')
            ->with($this->callback(function (MessageFilter $filter) use ($limit): bool {
                return $filter->getLastId() === 0 && $filter->getLimit() === $limit;
            }))
            ->willReturn($messageResult);

        $this->userMessageViewManager->expects(self::once())
            ->method('countViewsByMessageIds')
            ->with([$messageId])
            ->willReturn([$messageId => 40]);

        $this->linkTrackManager->expects(self::once())
            ->method('countUniqueClickersByMessageIds')
            ->with([$messageId])
            ->willReturn([$messageId => 10]);

        $result = $this->subject->getRecentCampaigns($limit);

        self::assertCount(1, $result);
        self::assertSame('Recent Campaign', $result[0]['name']);
        self::assertNull($result[0]['status']);
        self::assertSame('2023-02-01', $result[0]['date']);
        self::assertSame('40%', $result[0]['open_rate']);
        self::assertSame('10%', $result[0]['click_rate']);
    }
}
