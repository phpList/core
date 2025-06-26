<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Analytics\Model\LinkTrack;
use PhpList\Core\Domain\Analytics\Service\LinkTrackService;
use PhpList\Core\Domain\Messaging\Model\Message\MessageContent;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;

class MessageProcessingPreparatorTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private SubscriberRepository&MockObject $subscriberRepository;
    private MessageRepository&MockObject $messageRepository;
    private LinkTrackService&MockObject $linkTrackService;
    private OutputInterface&MockObject $output;
    private MessageProcessingPreparator $preparator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->linkTrackService = $this->createMock(LinkTrackService::class);
        $this->output = $this->createMock(OutputInterface::class);

        $this->preparator = new MessageProcessingPreparator(
            $this->entityManager,
            $this->subscriberRepository,
            $this->messageRepository,
            $this->linkTrackService
        );
    }

    public function testEnsureSubscribersHaveUuidWithNoSubscribers(): void
    {
        $this->subscriberRepository->expects($this->once())
            ->method('findSubscribersWithoutUuid')
            ->willReturn([]);

        $this->output->expects($this->never())
            ->method('writeln');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->preparator->ensureSubscribersHaveUuid($this->output);
    }

    public function testEnsureSubscribersHaveUuidWithSubscribers(): void
    {
        $subscriber1 = $this->createMock(Subscriber::class);
        $subscriber2 = $this->createMock(Subscriber::class);

        $subscribers = [$subscriber1, $subscriber2];

        $this->subscriberRepository->expects($this->once())
            ->method('findSubscribersWithoutUuid')
            ->willReturn($subscribers);

        $this->output->expects($this->once())
            ->method('writeln')
            ->with($this->stringContains('Giving a UUID to 2 subscribers'));

        $subscriber1->expects($this->once())
            ->method('setUniqueId')
            ->with($this->isType('string'));

        $subscriber2->expects($this->once())
            ->method('setUniqueId')
            ->with($this->isType('string'));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->preparator->ensureSubscribersHaveUuid($this->output);
    }

    public function testEnsureCampaignsHaveUuidWithNoCampaigns(): void
    {
        $this->messageRepository->expects($this->once())
            ->method('findCampaignsWithoutUuid')
            ->willReturn([]);

        $this->output->expects($this->never())
            ->method('writeln');

        $this->entityManager->expects($this->never())
            ->method('flush');

        $this->preparator->ensureCampaignsHaveUuid($this->output);
    }

    public function testEnsureCampaignsHaveUuidWithCampaigns(): void
    {
        $campaign1 = $this->createMock(Message::class);
        $campaign2 = $this->createMock(Message::class);

        $campaigns = [$campaign1, $campaign2];

        $this->messageRepository->expects($this->once())
            ->method('findCampaignsWithoutUuid')
            ->willReturn($campaigns);

        $this->output->expects($this->once())
            ->method('writeln')
            ->with($this->stringContains('Giving a UUID to 2 campaigns'));

        $campaign1->expects($this->once())
            ->method('setUuid')
            ->with($this->isType('string'));

        $campaign2->expects($this->once())
            ->method('setUuid')
            ->with($this->isType('string'));

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->preparator->ensureCampaignsHaveUuid($this->output);
    }

    public function testProcessMessageLinksWhenLinkTrackingNotApplicable(): void
    {
        $message = $this->createMock(Message::class);
        $userId = 123;

        $this->linkTrackService->expects($this->once())
            ->method('isExtractAndSaveLinksApplicable')
            ->willReturn(false);

        $this->linkTrackService->expects($this->never())
            ->method('extractAndSaveLinks');

        $message->expects($this->never())
            ->method('getContent');

        $result = $this->preparator->processMessageLinks($message, $userId);

        $this->assertSame($message, $result);
    }

    public function testProcessMessageLinksWhenNoLinksExtracted(): void
    {
        $message = $this->createMock(Message::class);
        $userId = 123;

        $this->linkTrackService->expects($this->once())
            ->method('isExtractAndSaveLinksApplicable')
            ->willReturn(true);

        $this->linkTrackService->expects($this->once())
            ->method('extractAndSaveLinks')
            ->with($message, $userId)
            ->willReturn([]);

        $message->expects($this->never())
            ->method('getContent');

        $result = $this->preparator->processMessageLinks($message, $userId);

        $this->assertSame($message, $result);
    }

    public function testProcessMessageLinksWithLinksExtracted(): void
    {
        $message = $this->createMock(Message::class);
        $content = $this->createMock(MessageContent::class);
        $userId = 123;

        $linkTrack1 = $this->createMock(LinkTrack::class);
        $linkTrack1->method('getId')->willReturn(1);
        $linkTrack1->method('getUrl')->willReturn('https://example.com');

        $linkTrack2 = $this->createMock(LinkTrack::class);
        $linkTrack2->method('getId')->willReturn(2);
        $linkTrack2->method('getUrl')->willReturn('https://example.org');

        $savedLinks = [$linkTrack1, $linkTrack2];

        $this->linkTrackService->method('isExtractAndSaveLinksApplicable')->willReturn(true);
        $this->linkTrackService->method('extractAndSaveLinks')->with($message, $userId)->willReturn($savedLinks);

        $message->method('getContent')->willReturn($content);

        $htmlContent = '<a href="https://example.com">Link 1</a> <a href="https://example.org">Link 2</a>';
        $content->method('getText')->willReturn($htmlContent);

        $footer = '<a href="https://example.com">Footer Link</a>';
        $content->method('getFooter')->willReturn($footer);

        $content->expects($this->once())
            ->method('setText')
            ->with($this->stringContains(MessageProcessingPreparator::LINT_TRACK_ENDPOINT . '?id=1'));

        $content->expects($this->once())
            ->method('setFooter')
            ->with($this->stringContains(MessageProcessingPreparator::LINT_TRACK_ENDPOINT . '?id=1'));

        $result = $this->preparator->processMessageLinks($message, $userId);

        $this->assertSame($message, $result);
    }
}
