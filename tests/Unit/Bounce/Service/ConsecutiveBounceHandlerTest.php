<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Bounce\Service;

use PhpList\Core\Bounce\Service\ConsecutiveBounceHandler;
use PhpList\Core\Bounce\Service\SubscriberBlacklistService;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Subscription\Model\Subscriber;
use PhpList\Core\Domain\Subscription\Repository\SubscriberRepository;
use PhpList\Core\Domain\Subscription\Service\Manager\SubscriberHistoryManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Translator;

class ConsecutiveBounceHandlerTest extends TestCase
{
    private BounceManager&MockObject $bounceManager;
    private SubscriberRepository&MockObject $subscriberRepository;
    private SubscriberHistoryManager&MockObject $subscriberHistoryManager;
    private SubscriberBlacklistService&MockObject $blacklistService;
    private SymfonyStyle&MockObject $io;
    private ConsecutiveBounceHandler $handler;

    protected function setUp(): void
    {
        $this->bounceManager = $this->createMock(BounceManager::class);
        $this->subscriberRepository = $this->createMock(SubscriberRepository::class);
        $this->subscriberHistoryManager = $this->createMock(SubscriberHistoryManager::class);
        $this->blacklistService = $this->createMock(SubscriberBlacklistService::class);
        $this->io = $this->createMock(SymfonyStyle::class);

        $this->io->method('section');
        $this->io->method('writeln');

        $unsubscribeThreshold = 2;
        $blacklistThreshold = 3;

        $this->handler = new ConsecutiveBounceHandler(
            bounceManager: $this->bounceManager,
            subscriberRepository: $this->subscriberRepository,
            subscriberHistoryManager: $this->subscriberHistoryManager,
            blacklistService: $this->blacklistService,
            translator: new Translator('en'),
            unsubscribeThreshold: $unsubscribeThreshold,
            blacklistThreshold: $blacklistThreshold,
        );
    }

    public function testHandleWithNoUsers(): void
    {
        $this->subscriberRepository
            ->expects($this->once())
            ->method('distinctUsersWithBouncesConfirmedNotBlacklisted')
            ->willReturn([]);

        $this->io->expects($this->once())->method('section')->with('Identifying consecutive bounces');
        $this->io->expects($this->once())->method('writeln')->with('Nothing to do');

        $this->handler->handle($this->io);
    }

    public function testUnsubscribeAtThresholdAddsHistoryAndMarksUnconfirmedOnce(): void
    {
        $user = $this->makeSubscriber(123);
        $this->subscriberRepository
            ->method('distinctUsersWithBouncesConfirmedNotBlacklisted')
            ->willReturn([$user]);

        $history = [
            ['um' => null, 'umb' => null, 'b' => $this->makeBounce(1)],
            ['um' => null, 'umb' => null, 'b' => $this->makeBounce(2)],
            ['um' => null, 'umb' => null, 'b' => $this->makeBounce(0)],
        ];
        $this->bounceManager
            ->expects($this->once())
            ->method('getUserMessageHistoryWithBounces')
            ->with($user)
            ->willReturn($history);

        $this->subscriberRepository
            ->expects($this->once())
            ->method('markUnconfirmed')
            ->with(123);

        $this->subscriberHistoryManager
            ->expects($this->once())
            ->method('addHistory')
            ->with(
                $user,
                'Auto unconfirmed',
                $this->stringContains('2 consecutive bounces')
            );

        $this->blacklistService->expects($this->never())->method('blacklist');

        $this->io->expects($this->once())->method('section')->with('Identifying consecutive bounces');
        $this->io->expects($this->once())->method('writeln')->with('Total of 1 subscribers processed');

        $this->handler->handle($this->io);
    }

    public function testBlacklistAtThresholdStopsProcessingAndAlsoUnsubscribesIfReached(): void
    {
        $user = $this->makeSubscriber(7);
        $this->subscriberRepository
            ->method('distinctUsersWithBouncesConfirmedNotBlacklisted')
            ->willReturn([$user]);

        $history = [
            ['um' => null, 'umb' => null, 'b' => $this->makeBounce(11)],
            ['um' => null, 'umb' => null, 'b' => $this->makeBounce(12)],
            ['um' => null, 'umb' => null, 'b' => $this->makeBounce(13)],
            // Any further entries should be ignored after blacklist stop
            ['um' => null, 'umb' => null, 'b' => $this->makeBounce(14)],
        ];
        $this->bounceManager
            ->expects($this->once())
            ->method('getUserMessageHistoryWithBounces')
            ->with($user)
            ->willReturn($history);

        // Unsubscribe reached at 2
        $this->subscriberRepository
            ->expects($this->once())
            ->method('markUnconfirmed')
            ->with(7);

        $this->subscriberHistoryManager
            ->expects($this->once())
            ->method('addHistory')
            ->with(
                $user,
                'Auto unconfirmed',
                $this->stringContains('consecutive bounces')
            );

        // Blacklist at 3
        $this->blacklistService
            ->expects($this->once())
            ->method('blacklist')
            ->with(
                $user,
                $this->stringContains('3 consecutive bounces')
            );

        $this->handler->handle($this->io);
    }

    public function testDuplicateBouncesAreIgnoredInCounting(): void
    {
        $user = $this->makeSubscriber(55);
        $this->subscriberRepository->method('distinctUsersWithBouncesConfirmedNotBlacklisted')->willReturn([$user]);

        // First is duplicate (by status), ignored; then two real => unsubscribe triggered once
        $history = [
            ['um' => null, 'umb' => null, 'b' => $this->makeBounce(101, status: 'DUPLICATE bounce')],
            ['um' => null, 'umb' => null, 'b' => $this->makeBounce(102, comment: 'ok')],
            ['um' => null, 'umb' => null, 'b' => $this->makeBounce(103)],
        ];
        $this->bounceManager->method('getUserMessageHistoryWithBounces')->willReturn($history);

        $this->subscriberRepository->expects($this->once())->method('markUnconfirmed')->with(55);
        $this->subscriberHistoryManager->expects($this->once())->method('addHistory')->with(
            $user,
            'Auto unconfirmed',
            $this->stringContains('2 consecutive bounces')
        );
        $this->blacklistService->expects($this->never())->method('blacklist');

        $this->handler->handle($this->io);
    }

    public function testBreaksOnBounceWithoutRealId(): void
    {
        $user = $this->makeSubscriber(77);
        $this->subscriberRepository->method('distinctUsersWithBouncesConfirmedNotBlacklisted')->willReturn([$user]);

        // The first entry has null bounce (no real id) => processing for the user stops immediately; no actions
        $history = [
            ['um' => null, 'umb' => null, 'b' => null],
            // should not be reached
            ['um' => null, 'umb' => null, 'b' => $this->makeBounce(1)],
        ];
        $this->bounceManager->method('getUserMessageHistoryWithBounces')->willReturn($history);

        $this->subscriberRepository->expects($this->never())->method('markUnconfirmed');
        $this->subscriberHistoryManager->expects($this->never())->method('addHistory');
        $this->blacklistService->expects($this->never())->method('blacklist');

        $this->handler->handle($this->io);
    }

    private function makeSubscriber(int $id): Subscriber
    {
        $subscriber = $this->createMock(Subscriber::class);
        $subscriber->method('getId')->willReturn($id);

        return $subscriber;
    }

    private function makeBounce(int $id, ?string $status = null, ?string $comment = null): Bounce
    {
        $bounce = $this->createMock(Bounce::class);
        $bounce->method('getId')->willReturn($id);
        $bounce->method('getStatus')->willReturn($status);
        $bounce->method('getComment')->willReturn($comment);

        return $bounce;
    }
}
