<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Identity\Service\AdminNotifier;
use PhpList\Core\Domain\Messaging\Exception\EmailBlacklistedException;
use PhpList\Core\Domain\Messaging\Exception\InvalidRecipientOrSubjectException;
use PhpList\Core\Domain\Messaging\Exception\MessageCacheMissingException;
use PhpList\Core\Domain\Messaging\Model\Dto\MessageForwardDto;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Configuration\Model\OutputFormat;
use PhpList\Core\Domain\Messaging\Service\ForwardContentService;
use PhpList\Core\Domain\Messaging\Service\ForwardDeliveryService;
use PhpList\Core\Domain\Messaging\Service\ForwardingGuard;
use PhpList\Core\Domain\Messaging\Service\ForwardingStatsService;
use PhpList\Core\Domain\Messaging\Service\MessageDataLoader;
use PhpList\Core\Domain\Messaging\Service\MessageForwardService;
use PhpList\Core\Domain\Messaging\Service\MessagePrecacheService;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberListRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mime\Email;

class MessageForwardServiceTest extends TestCase
{
    private ForwardingGuard&MockObject $guard;
    private ForwardDeliveryService&MockObject $delivery;
    private MessageDataLoader&MockObject $loader;
    private SubscriberListRepository&MockObject $listRepo;
    private ForwardContentService&MockObject $contentService;
    private MessagePrecacheService&MockObject $precache;
    private AdminNotifier&MockObject $notifier;
    private ForwardingStatsService&MockObject $stats;

    protected function setUp(): void
    {
        $this->guard = $this->createMock(ForwardingGuard::class);
        $this->delivery = $this->createMock(ForwardDeliveryService::class);
        $this->loader = $this->createMock(MessageDataLoader::class);
        $this->listRepo = $this->createMock(SubscriberListRepository::class);
        $this->contentService = $this->createMock(ForwardContentService::class);
        $this->precache = $this->createMock(MessagePrecacheService::class);
        $this->notifier = $this->createMock(AdminNotifier::class);
        $this->stats = $this->createMock(ForwardingStatsService::class);
    }

    private function createService(): MessageForwardService
    {
        return new MessageForwardService(
            guard: $this->guard,
            forwardDeliveryService: $this->delivery,
            messageDataLoader: $this->loader,
            subscriberListRepository: $this->listRepo,
            forwardContentService: $this->contentService,
            precacheService: $this->precache,
            adminNotifier: $this->notifier,
            forwardingStatsService: $this->stats,
        );
    }

    private function createDto(array $emails): MessageForwardDto
    {
        return new MessageForwardDto(
            emails: $emails,
            uid: 'uid-123',
            fromName: 'Alice',
            fromEmail: 'alice@example.test'
        );
    }

    public function testSkipsAlreadySentAndStillUpdatesStats(): void
    {
        $service = $this->createService();
        $campaign = $this->createMock(Message::class);
        $subscriber = new Subscriber();

        $this->loader->expects(self::once())
            ->method('__invoke')
            ->with(self::identicalTo($campaign))
            ->willReturn(['loaded' => true]);

        $this->guard->expects(self::once())
            ->method('assertCanForward')
            ->willReturn($subscriber);

        $this->listRepo->expects(self::once())
            ->method('getListsByMessage')
            ->with(self::identicalTo($campaign))
            ->willReturn([]);

        $this->guard->expects(self::exactly(2))
            ->method('hasAlreadyBeenSent')
            ->willReturn(true);

        $this->precache->expects(self::never())->method('precacheMessage');
        $this->contentService->expects(self::never())->method('getContents');
        $this->delivery->expects(self::never())->method('send');
        $this->delivery->expects(self::never())->method('markSent');
        $this->delivery->expects(self::never())->method('markFailed');
        $this->notifier->expects(self::never())->method('notifyForwardSucceeded');
        $this->notifier->expects(self::never())->method('notifyForwardFailed');
        $this->stats->expects(self::never())->method('incrementFriendsCount');
        $this->stats->expects(self::once())
            ->method('updateFriendsCount')
            ->with(self::identicalTo($subscriber));

        $service->forward($this->createDto(['a@x.tld', 'b@x.tld']), $campaign);
    }

    public function testPrecacheFailureNotifiesAndMarksFailed(): void
    {
        $service = $this->createService();
        $campaign = $this->createMock(Message::class);
        $subscriber = new Subscriber();

        $this->loader->method('__invoke')->willReturn(['ok' => true]);
        $this->guard->method('assertCanForward')->willReturn($subscriber);
        $this->listRepo->method('getListsByMessage')->willReturn(['L1']);
        $this->guard->method('hasAlreadyBeenSent')->willReturn(false);

        $this->precache->expects(self::once())
            ->method('precacheMessage')
            ->with(self::identicalTo($campaign), ['ok' => true], true)
            ->willReturn(false);

        $this->notifier->expects(self::once())->method('notifyForwardFailed');
        $this->delivery->expects(self::once())->method('markFailed');
        $this->contentService->expects(self::never())->method('getContents');
        $this->delivery->expects(self::never())->method('send');
        $this->stats->expects(self::never())->method('incrementFriendsCount');
        $this->stats->expects(self::once())
            ->method('updateFriendsCount')
            ->with(self::identicalTo($subscriber));

        $service->forward($this->createDto(['friend@example.test']), $campaign);
    }

    public function testSuccessfulFlowSendsAndUpdatesEverything(): void
    {
        $service = $this->createService();
        $campaign = $this->createMock(Message::class);
        $subscriber = new Subscriber();

        $this->loader->method('__invoke')->willReturn(['ok' => true]);
        $this->guard->method('assertCanForward')->willReturn($subscriber);
        $this->listRepo->method('getListsByMessage')->willReturn([]);
        $this->guard->method('hasAlreadyBeenSent')->willReturn(false);
        $this->precache->method('precacheMessage')->willReturn(true);

        $email1 = (new Email())->to('x1@example.test');
        $email2 = (new Email())->to('x2@example.test');

        $this->contentService->expects(self::exactly(2))
            ->method('getContents')
            ->willReturnOnConsecutiveCalls([$email1, OutputFormat::Html], [$email2, OutputFormat::Text]);

        $this->delivery->expects(self::exactly(2))->method('send');
        $this->notifier->expects(self::exactly(2))->method('notifyForwardSucceeded');
        $this->delivery->expects(self::exactly(2))->method('markSent');

        // Campaign should increment sent count for both sentAs values
        $campaign->expects(self::exactly(2))
            ->method('incrementSentCount')
            ->with(self::logicalOr(OutputFormat::Html, OutputFormat::Text));

        // Stats increment per friend, then update once at the end
        $this->stats->expects(self::exactly(2))
            ->method('incrementFriendsCount')
            ->with(self::identicalTo($subscriber));
        $this->stats->expects(self::once())
            ->method('updateFriendsCount')
            ->with(self::identicalTo($subscriber));

        $service->forward($this->createDto(['x1@example.test', 'x2@example.test']), $campaign);
    }

    public function testGetContentsThrowsEmailBlacklistedIsHandledAsFailureAndReportedInResult(): void
    {
        $service = $this->createService();
        $campaign = $this->createMock(Message::class);
        $subscriber = new Subscriber();

        $this->loader->method('__invoke')->willReturn(['ok' => true]);
        $this->guard->method('assertCanForward')->willReturn($subscriber);
        $this->listRepo->method('getListsByMessage')->willReturn(['L1']);
        $this->guard->method('hasAlreadyBeenSent')->willReturn(false);
        $this->precache->method('precacheMessage')->willReturn(true);

        $this->contentService->method('getContents')->willThrowException(new EmailBlacklistedException());

        $this->notifier->expects(self::once())->method('notifyForwardFailed');
        $this->delivery->expects(self::once())->method('markFailed');
        $this->delivery->expects(self::never())->method('send');
        $this->delivery->expects(self::never())->method('markSent');
        $this->stats->expects(self::never())->method('incrementFriendsCount');
        $this->stats->expects(self::once())->method('updateFriendsCount');

        $result = $service->forward($this->createDto(['friend1@example.test']), $campaign);

        self::assertSame(1, $result->totalRequested);
        self::assertSame(0, $result->totalSent);
        self::assertSame(1, $result->totalFailed);
        self::assertSame(0, $result->totalAlreadySent);
        self::assertCount(1, $result->recipients);
        self::assertSame('friend1@example.test', $result->recipients[0]->email);
        self::assertSame('failed', $result->recipients[0]->status);
        self::assertSame('Email address is blacklisted.', $result->recipients[0]->reason);
    }

    public function testGetContentsThrowsInvalidRecipientIsHandledAsFailureAndReportedInResult(): void
    {
        $service = $this->createService();
        $campaign = $this->createMock(Message::class);
        $subscriber = new Subscriber();

        $this->loader->method('__invoke')->willReturn(['ok' => true]);
        $this->guard->method('assertCanForward')->willReturn($subscriber);
        $this->listRepo->method('getListsByMessage')->willReturn([]);
        $this->guard->method('hasAlreadyBeenSent')->willReturn(false);
        $this->precache->method('precacheMessage')->willReturn(true);

        $this->contentService->method('getContents')->willThrowException(new InvalidRecipientOrSubjectException());

        $this->notifier->expects(self::once())->method('notifyForwardFailed');
        $this->delivery->expects(self::once())->method('markFailed');
        $this->delivery->expects(self::never())->method('send');
        $this->delivery->expects(self::never())->method('markSent');
        $this->stats->expects(self::never())->method('incrementFriendsCount');
        $this->stats->expects(self::once())->method('updateFriendsCount');

        $result = $service->forward($this->createDto(['friend2@example.test']), $campaign);

        self::assertSame(1, $result->totalRequested);
        self::assertSame(0, $result->totalSent);
        self::assertSame(1, $result->totalFailed);
        self::assertSame(0, $result->totalAlreadySent);
        self::assertSame('Invalid recipient or subject.', $result->recipients[0]->reason);
        self::assertSame('failed', $result->recipients[0]->status);
    }

    public function testPrecacheFailureAlsoReflectedInForwardingResult(): void
    {
        $service = $this->createService();
        $campaign = $this->createMock(Message::class);
        $subscriber = new Subscriber();

        $this->loader->method('__invoke')->willReturn(['data' => true]);
        $this->guard->method('assertCanForward')->willReturn($subscriber);
        $this->listRepo->method('getListsByMessage')->willReturn(['LZ']);
        $this->guard->method('hasAlreadyBeenSent')->willReturn(false);

        $this->precache->method('precacheMessage')->willReturn(false);

        $this->notifier->expects(self::once())->method('notifyForwardFailed');
        $this->delivery->expects(self::once())->method('markFailed');
        $this->contentService->expects(self::never())->method('getContents');

        $result = $service->forward($this->createDto(['friend3@example.test']), $campaign);

        self::assertSame(1, $result->totalRequested);
        self::assertSame(0, $result->totalSent);
        self::assertSame(1, $result->totalFailed);
        self::assertSame(0, $result->totalAlreadySent);
        self::assertSame('precache_failed', $result->recipients[0]->reason);
        self::assertSame('failed', $result->recipients[0]->status);
    }

    public function testMixedScenarioAggregatesResultsAndSideEffects(): void
    {
        $service = $this->createService();
        $campaign = $this->createMock(Message::class);
        $subscriber = new Subscriber();

        $this->loader->method('__invoke')->willReturn(['ok' => 1]);
        $this->guard->method('assertCanForward')->willReturn($subscriber);
        $this->listRepo->method('getListsByMessage')->willReturn(['L1', 'L2']);

        // e1 already sent, others not
        $this->guard->expects(self::exactly(4))
            ->method('hasAlreadyBeenSent')
            ->willReturnOnConsecutiveCalls(true, false, false, false);

        // precache called for e2, e3, e4
        $this->precache->expects(self::exactly(3))
            ->method('precacheMessage')
            ->willReturnOnConsecutiveCalls(false, true, true);

        // e3 success, e4 throws
        $email3 = (new Email())->to('e3@example.test');
        $this->contentService->expects(self::exactly(2))
            ->method('getContents')
            ->willReturnOnConsecutiveCalls(
                [$email3, OutputFormat::Html],
                self::throwException(new MessageCacheMissingException())
            );

        // side-effects
        $this->delivery->expects(self::once())->method('send');
        $this->delivery->expects(self::once())->method('markSent');
        $this->delivery->expects(self::exactly(2))->method('markFailed');
        $this->notifier->expects(self::once())->method('notifyForwardSucceeded');
        $this->notifier->expects(self::exactly(2))->method('notifyForwardFailed');
        $campaign->expects(self::once())
            ->method('incrementSentCount')
            ->with(OutputFormat::Html);
        $this->stats->expects(self::once())
            ->method('incrementFriendsCount')
            ->with(self::identicalTo($subscriber));
        $this->stats->expects(self::once())
            ->method('updateFriendsCount')
            ->with(self::identicalTo($subscriber));

        $dto = $this->createDto(['e1@example.test', 'e2@example.test', 'e3@example.test', 'e4@example.test']);
        $result = $service->forward($dto, $campaign);

        self::assertSame(4, $result->totalRequested);
        self::assertSame(1, $result->totalSent);
        self::assertSame(2, $result->totalFailed);
        self::assertSame(1, $result->totalAlreadySent);

        self::assertCount(4, $result->recipients);
        self::assertSame('already_sent', $result->recipients[0]->status);
        self::assertSame('failed', $result->recipients[1]->status);
        self::assertSame('precache_failed', $result->recipients[1]->reason);
        self::assertSame('sent', $result->recipients[2]->status);
        self::assertSame('failed', $result->recipients[3]->status);
        self::assertSame('Message cache is missing or expired.', $result->recipients[3]->reason);
    }
}
