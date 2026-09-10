<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\CampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\Message\UserMessageStatus;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Messaging\Service\CampaignExclusionService;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Service\Provider\SubscriberProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CampaignExclusionServiceTest extends TestCase
{
    private SubscriberProvider|MockObject $subscriberProvider;
    private UserMessageRepository|MockObject $userMessageRepository;

    protected function setUp(): void
    {
        $this->subscriberProvider = $this->createMock(SubscriberProvider::class);
        $this->userMessageRepository = $this->createMock(UserMessageRepository::class);
    }

    private function createService(bool $useListExclude): CampaignExclusionService
    {
        return new CampaignExclusionService(
            $this->subscriberProvider,
            $this->userMessageRepository,
            $useListExclude,
        );
    }

    public function testResolveExcludeListIdsReturnsEmptyWhenDisabled(): void
    {
        $service = $this->createService(useListExclude: false);

        $this->assertSame([], $service->resolveExcludeListIds(['excludelist' => [55 => 1, 66 => 1]]));
    }

    public function testResolveExcludeListIdsReturnsNumericKeysWhenEnabled(): void
    {
        $service = $this->createService(useListExclude: true);

        $this->assertSame([55, 66], $service->resolveExcludeListIds(['excludelist' => [55 => 1, 66 => 1]]));
    }

    public function testResolveExcludeListIdsReturnsEmptyWhenNoExcludeListPresent(): void
    {
        $service = $this->createService(useListExclude: true);

        $this->assertSame([], $service->resolveExcludeListIds([]));
    }

    public function testMarkExcludedSubscribersMarksSendableRecipientAsExcluded(): void
    {
        $service = $this->createService(useListExclude: true);

        $campaign = $this->createMock(Message::class);
        $data = new CampaignProcessorMessage(1);

        $excludedSubscriber = $this->createMock(Subscriber::class);
        $excludedSubscriber->method('getEmail')->willReturn('excluded@example.com');

        $this->subscriberProvider->expects($this->once())
            ->method('getExcludedSubscribers')
            ->with([55])
            ->willReturn([$excludedSubscriber]);

        $this->subscriberProvider->expects($this->once())
            ->method('getSendableSubscribersForMessageOrLists')
            ->with($data, $campaign)
            ->willReturn(['excluded@example.com' => $excludedSubscriber]);

        $this->userMessageRepository->expects($this->once())
            ->method('findByUserAndMessage')
            ->with($excludedSubscriber, $campaign)
            ->willReturn(null);

        $this->userMessageRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(
                fn (UserMessage $userMessage): bool => $userMessage->getUser() === $excludedSubscriber
                    && $userMessage->getStatus() === UserMessageStatus::Excluded
            ));

        $service->markExcludedSubscribers($campaign, $data, [55]);
    }

    public function testMarkExcludedSubscribersDoesNothingWhenExcludeListIdsEmpty(): void
    {
        $service = $this->createService(useListExclude: true);

        $this->subscriberProvider->expects($this->never())->method('getExcludedSubscribers');

        $service->markExcludedSubscribers($this->createMock(Message::class), new CampaignProcessorMessage(1), []);
    }

    public function testMarkExcludedSubscribersDoesNotOverwriteExistingNonTodoUserMessage(): void
    {
        $service = $this->createService(useListExclude: true);

        $campaign = $this->createMock(Message::class);
        $data = new CampaignProcessorMessage(1);

        $excludedSubscriber = $this->createMock(Subscriber::class);
        $excludedSubscriber->method('getEmail')->willReturn('already-sent@example.com');

        $this->subscriberProvider->method('getExcludedSubscribers')->willReturn([$excludedSubscriber]);
        $this->subscriberProvider->method('getSendableSubscribersForMessageOrLists')
            ->willReturn(['already-sent@example.com' => $excludedSubscriber]);

        $existingUserMessage = $this->createMock(UserMessage::class);
        $existingUserMessage->method('getStatus')->willReturn(UserMessageStatus::Sent);

        $this->userMessageRepository->expects($this->once())
            ->method('findByUserAndMessage')
            ->willReturn($existingUserMessage);

        $this->userMessageRepository->expects($this->never())->method('save');

        $service->markExcludedSubscribers($campaign, $data, [55]);
    }

    public function testMarkExcludedSubscribersSkipsSubscriberWhoIsNotACampaignRecipient(): void
    {
        $service = $this->createService(useListExclude: true);

        $campaign = $this->createMock(Message::class);
        $data = new CampaignProcessorMessage(1);

        $nonRecipient = $this->createMock(Subscriber::class);
        $nonRecipient->method('getEmail')->willReturn('not-a-recipient@example.com');

        $this->subscriberProvider->method('getExcludedSubscribers')->willReturn([$nonRecipient]);
        $this->subscriberProvider->method('getSendableSubscribersForMessageOrLists')->willReturn([]);

        $this->userMessageRepository->expects($this->never())->method('findByUserAndMessage');
        $this->userMessageRepository->expects($this->never())->method('save');

        $service->markExcludedSubscribers($campaign, $data, [55]);
    }
}
