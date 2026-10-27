<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Analytics\Service\LinkTrackService;
use PhpList\Core\Domain\Messaging\Model\Dto\MessagePrecacheDto;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Translation\Translator;

class MessageProcessingPreparatorTest extends TestCase
{
    private SubscriberRepository&MockObject $subscriberRepository;
    private MessageRepository&MockObject $messageRepository;
    private LinkTrackService&MockObject $linkTrackService;
    private OutputInterface&MockObject $output;
    private MessageProcessingPreparator $preparator;

    protected function setUp(): void
    {
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->linkTrackService = $this->createMock(LinkTrackService::class);
        $this->output = $this->createMock(OutputInterface::class);

        $this->preparator = new MessageProcessingPreparator(
            subscriberRepository: $this->subscriberRepository,
            messageRepository: $this->messageRepository,
            linkTrackService: $this->linkTrackService,
            translator: new Translator('en'),
        );
    }

    public function testEnsureSubscribersHaveUuidWithNoSubscribers(): void
    {
        $this->subscriberRepository->expects($this->once())
            ->method('findSubscribersWithoutUuid')
            ->willReturn([]);

        $this->output->expects($this->never())
            ->method('writeln');

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

        $this->preparator->ensureSubscribersHaveUuid($this->output);
    }

    public function testEnsureCampaignsHaveUuidWithNoCampaigns(): void
    {
        $this->messageRepository->expects($this->once())
            ->method('findCampaignsWithoutUuid')
            ->willReturn([]);

        $this->output->expects($this->never())
            ->method('writeln');

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

        $this->preparator->ensureCampaignsHaveUuid($this->output);
    }

    public function testProcessMessageLinksWhenClickTrackingIsDisabled(): void
    {
        $messageContent = new MessagePrecacheDto();
        $subscriber = $this->createMock(Subscriber::class);

        $this->linkTrackService->expects($this->once())
            ->method('isClickTrackingEnabled')
            ->willReturn(false);
        $this->messageRepository->expects($this->never())->method('findById');
        $this->linkTrackService->expects($this->never())->method('trackLinks');

        $result = $this->preparator->processMessageLinks(1, $messageContent, $subscriber);

        $this->assertSame($messageContent, $result);
    }

    public function testProcessMessageLinksWhenCampaignIsUnknown(): void
    {
        $messageContent = new MessagePrecacheDto();
        $subscriber = $this->createMock(Subscriber::class);

        $this->linkTrackService->method('isClickTrackingEnabled')->willReturn(true);
        $this->messageRepository->expects($this->once())->method('findById')->with(1)->willReturn(null);
        $this->linkTrackService->expects($this->never())->method('trackLinks');

        $result = $this->preparator->processMessageLinks(1, $messageContent, $subscriber);

        $this->assertSame($messageContent, $result);
    }

    public function testProcessMessageLinksReturnsTheContentWithTrackedLinks(): void
    {
        $messageContent = new MessagePrecacheDto();
        $tracked = new MessagePrecacheDto();
        $subscriber = $this->createMock(Subscriber::class);
        $campaign = $this->createMock(Message::class);

        $this->linkTrackService->method('isClickTrackingEnabled')->willReturn(true);
        $this->messageRepository->method('findById')->with(1)->willReturn($campaign);
        $this->linkTrackService->expects($this->once())
            ->method('trackLinks')
            ->with($messageContent, $campaign, $subscriber)
            ->willReturn($tracked);

        $result = $this->preparator->processMessageLinks(1, $messageContent, $subscriber);

        $this->assertSame($tracked, $result);
    }
}
