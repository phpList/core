<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Analytics\Service;

use PhpList\Core\Core\ParameterProvider;
use PhpList\Core\Domain\Analytics\Model\Dto\LinkTrackToken;
use PhpList\Core\Domain\Analytics\Model\LinkTrackForward;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackForwardRepository;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackMlRepository;
use PhpList\Core\Domain\Analytics\Repository\LinkTrackUmlClickRepository;
use PhpList\Core\Domain\Analytics\Service\LinkClickService;
use PhpList\Core\Domain\Analytics\Service\LinkTrackTokenService;
use PhpList\Core\Domain\Analytics\Service\UserMessageService;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Model\UserMessage;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Repository\UserMessageRepository;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LinkClickServiceTest extends TestCase
{
    private LinkTrackForwardRepository&MockObject $forwardRepository;
    private LinkTrackMlRepository&MockObject $mlRepository;
    private LinkTrackUmlClickRepository&MockObject $umlClickRepository;
    private MessageRepository&MockObject $messageRepository;
    private SubscriberRepository&MockObject $subscriberRepository;
    private UserMessageRepository&MockObject $userMessageRepository;
    private UserMessageService&MockObject $userMessageService;
    private LinkTrackTokenService $tokenService;
    private LinkClickService $service;
    private Message&MockObject $message;
    private Subscriber&MockObject $subscriber;

    protected function setUp(): void
    {
        $this->forwardRepository = $this->createMock(LinkTrackForwardRepository::class);
        $this->mlRepository = $this->createMock(LinkTrackMlRepository::class);
        $this->umlClickRepository = $this->createMock(LinkTrackUmlClickRepository::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->userMessageRepository = $this->createMock(UserMessageRepository::class);
        $this->userMessageService = $this->createMock(UserMessageService::class);
        $this->tokenService = new LinkTrackTokenService(new ParameterProvider([]), 'https://example.com/api/v2', 's');

        $this->service = new LinkClickService(
            $this->tokenService,
            $this->forwardRepository,
            $this->mlRepository,
            $this->umlClickRepository,
            $this->messageRepository,
            $this->subscriberRepository,
            $this->userMessageRepository,
            $this->userMessageService,
        );

        $this->message = $this->createMock(Message::class);
        $this->message->method('getId')->willReturn(7);
        $this->subscriber = $this->createMock(Subscriber::class);
        $this->subscriber->method('getId')->willReturn(3);
        $this->subscriber->method('getUniqueId')->willReturn('uniq-3');
    }

    private function tid(string $type = 'H'): string
    {
        $url = $this->tokenService->createUrl(new LinkTrackToken($type, 'fwd', 'msg', 'usr'));
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        return $query['tid'];
    }

    private function givenKnownLink(string $url = 'https://example.com/a', bool $personalise = false): void
    {
        $forward = $this->createMock(LinkTrackForward::class);
        $forward->method('getId')->willReturn(11);
        $forward->method('getUrl')->willReturn($url);
        $forward->method('isPersonalise')->willReturn($personalise);

        $this->forwardRepository->method('findOneByUuid')->with('fwd')->willReturn($forward);
        $this->messageRepository->method('findOneBy')->with(['uuid' => 'msg'])->willReturn($this->message);
        $this->subscriberRepository->method('findOneBy')->with(['uuid' => 'usr'])->willReturn($this->subscriber);
    }

    private function givenUserMessage(bool $viewed): void
    {
        $userMessage = $this->createMock(UserMessage::class);
        $userMessage->method('isViewed')->willReturn($viewed);
        $this->userMessageRepository->method('findByUserAndMessage')->willReturn($userMessage);
    }

    public function testRedirectsToTheStoredUrlAndCountsTheClick(): void
    {
        $this->givenKnownLink();
        $this->givenUserMessage(viewed: false);

        $this->mlRepository->expects(self::once())
            ->method('recordClick')
            ->with(7, 11, true, self::anything());
        $this->umlClickRepository->expects(self::once())
            ->method('recordClick')
            ->with(7, 3, 11, true, self::anything());
        $this->userMessageService->expects(self::once())
            ->method('trackUserMessageView')
            ->with('uniq-3', 7, ['HTTP_USER_AGENT' => 'ua']);

        self::assertSame(
            'https://example.com/a',
            $this->service->trackClick($this->tid(), null, ['HTTP_USER_AGENT' => 'ua'])
        );
    }

    public function testTextClicksAreCountedAsText(): void
    {
        $this->givenKnownLink();
        $this->givenUserMessage(viewed: true);

        $this->mlRepository->expects(self::once())->method('recordClick')->with(7, 11, false, self::anything());
        $this->umlClickRepository->expects(self::once())
            ->method('recordClick')
            ->with(7, 3, 11, false, self::anything());

        self::assertNotNull($this->service->trackClick($this->tid('T'), null, []));
    }

    public function testOnlyTheFirstClickCountsAsView(): void
    {
        $this->givenKnownLink();
        $this->givenUserMessage(viewed: true);

        $this->userMessageService->expects(self::never())->method('trackUserMessageView');

        self::assertNotNull($this->service->trackClick($this->tid(), null, []));
    }

    public function testPersonalisedLinkGetsTheSubscribersUid(): void
    {
        $this->givenKnownLink('https://example.com/p?a=1', personalise: true);
        $this->givenUserMessage(viewed: true);

        self::assertSame('https://example.com/p?a=1&uid=uniq-3', $this->service->trackClick($this->tid(), null, []));
    }

    public function testPersonalisedLinkIsRefusedForSubscribersThatNeverGotTheCampaign(): void
    {
        $this->givenKnownLink('https://example.com/p', personalise: true);
        $this->userMessageRepository->method('findByUserAndMessage')->willReturn(null);

        $this->mlRepository->expects(self::never())->method('recordClick');

        self::assertNull($this->service->trackClick($this->tid(), null, []));
    }

    public function testNonPersonalisedLinkWorksWithoutUserMessage(): void
    {
        $this->givenKnownLink();
        $this->userMessageRepository->method('findByUserAndMessage')->willReturn(null);

        $this->userMessageService->expects(self::never())->method('trackUserMessageView');

        self::assertSame('https://example.com/a', $this->service->trackClick($this->tid(), null, []));
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->forwardRepository->expects(self::never())->method('findOneByUuid');

        self::assertNull($this->service->trackClick('garbage', null, []));
    }

    public function testUnknownForwardIsRejected(): void
    {
        $this->forwardRepository->method('findOneByUuid')->willReturn(null);
        $this->mlRepository->expects(self::never())->method('recordClick');

        self::assertNull($this->service->trackClick($this->tid(), null, []));
    }

    public function testUnknownSubscriberIsRejected(): void
    {
        $forward = $this->createMock(LinkTrackForward::class);
        $forward->method('getUrl')->willReturn('https://example.com/a');
        $this->forwardRepository->method('findOneByUuid')->willReturn($forward);
        $this->messageRepository->method('findOneBy')->willReturn($this->message);
        $this->subscriberRepository->method('findOneBy')->willReturn(null);
        $this->mlRepository->expects(self::never())->method('recordClick');

        self::assertNull($this->service->trackClick($this->tid(), null, []));
    }

    public function testNeverRedirectsToNonWebUrls(): void
    {
        $this->givenKnownLink('javascript:alert(1)');
        $this->mlRepository->expects(self::never())->method('recordClick');

        self::assertNull($this->service->trackClick($this->tid(), null, []));
    }
}
