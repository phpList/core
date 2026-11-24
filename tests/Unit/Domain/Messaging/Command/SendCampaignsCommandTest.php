<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Command\SendCampaignsCommand;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessor\SyncCampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SendCampaignsCommandTest extends TestCase
{
    private MessageRepository&MockObject $messageRepository;
    private MessageBusInterface&MockObject $messageBus;
    private SharedLockInterface&MockObject $lock;
    private ConfigProvider&MockObject $configProvider;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->lock = $this->createMock(SharedLockInterface::class);
        $this->configProvider = $this->createMock(ConfigProvider::class);
        $lockFactory = $this->createMock(LockFactory::class);
        $lockFactory->method('createLock')->with('queue_processor')->willReturn($this->lock);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $this->tester = new CommandTester(new SendCampaignsCommand(
            $this->messageRepository,
            $lockFactory,
            $this->createMock(MessageProcessingPreparator::class),
            $this->messageBus,
            $this->configProvider,
            $translator,
            $this->createMock(EntityManagerInterface::class),
        ));
    }

    private function campaign(int $id): Message&MockObject
    {
        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn($id);

        return $campaign;
    }

    public function testFailsWhenLocked(): void
    {
        $this->lock->method('acquire')->willReturn(false);
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->tester->execute([]);

        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testStopsInMaintenanceModeAndReleasesLock(): void
    {
        $this->lock->method('acquire')->willReturn(true);
        $this->lock->expects($this->once())->method('release');
        $this->configProvider->method('isEnabled')->willReturn(true);
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->tester->execute([]);

        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testRejectsInvalidCampaignOption(): void
    {
        $this->lock->expects($this->never())->method('acquire');

        $this->tester->execute(['--campaign' => 'abc']);

        $this->assertSame(2, $this->tester->getStatusCode());
    }

    public function testSendsAllDueCampaignsSynchronously(): void
    {
        $this->lock->method('acquire')->willReturn(true);
        $this->lock->expects($this->once())->method('release');
        $this->messageRepository->method('getByStatusAndEmbargo')
            ->willReturn([$this->campaign(1), $this->campaign(2)]);

        $ids = [];
        $this->messageBus->expects($this->exactly(2))->method('dispatch')
            ->willReturnCallback(function (SyncCampaignProcessorMessage $message) use (&$ids) {
                $ids[] = $message->getMessageId();

                return new Envelope($message);
            });

        $this->tester->execute([]);

        $this->assertSame([1, 2], $ids);
        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testCampaignOptionLimitsToOneCampaign(): void
    {
        $this->lock->method('acquire')->willReturn(true);
        $this->messageRepository->method('getByStatusAndEmbargo')
            ->willReturn([$this->campaign(1), $this->campaign(2)]);

        $this->messageBus->expects($this->once())->method('dispatch')
            ->with($this->callback(fn (SyncCampaignProcessorMessage $m) => $m->getMessageId() === 2))
            ->willReturn(new Envelope(new SyncCampaignProcessorMessage(2)));

        $this->tester->execute(['--campaign' => '2']);

        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testUnknownCampaignFails(): void
    {
        $this->lock->method('acquire')->willReturn(true);
        $this->lock->expects($this->once())->method('release');
        $this->messageRepository->method('getByStatusAndEmbargo')->willReturn([$this->campaign(1)]);
        $this->messageBus->expects($this->never())->method('dispatch');

        $this->tester->execute(['--campaign' => '9']);

        $this->assertSame(1, $this->tester->getStatusCode());
    }

    public function testDispatchFailureContinuesAndReturnsFailure(): void
    {
        $this->lock->method('acquire')->willReturn(true);
        $this->messageRepository->method('getByStatusAndEmbargo')
            ->willReturn([$this->campaign(1), $this->campaign(2)]);
        $this->messageBus->expects($this->exactly(2))->method('dispatch')
            ->willReturnCallback(function (SyncCampaignProcessorMessage $message) {
                if ($message->getMessageId() === 1) {
                    throw new Exception('boom');
                }

                return new Envelope($message);
            });

        $this->tester->execute([]);

        $this->assertSame(1, $this->tester->getStatusCode());
        $this->assertStringContainsString('boom', $this->tester->getDisplay());
    }
}
