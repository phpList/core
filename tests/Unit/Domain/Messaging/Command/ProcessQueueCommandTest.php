<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpList\Core\Domain\Configuration\Service\Provider\ConfigProvider;
use PhpList\Core\Domain\Messaging\Command\ProcessQueueCommand;
use PhpList\Core\Domain\Messaging\Message\CampaignProcessorMessage;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Translation\Translator;

class ProcessQueueCommandTest extends TestCase
{
    private MessageRepository&MockObject $messageRepository;
    private MessageProcessingPreparator&MockObject $messageProcessingPreparator;
    private MessageBusInterface&MockObject $messageBus;
    private LockInterface&MockObject $lock;
    private CommandTester $commandTester;
    private Translator&MockObject $translator;

    protected function setUp(): void
    {
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $lockFactory = $this->createMock(LockFactory::class);
        $this->messageProcessingPreparator = $this->createMock(MessageProcessingPreparator::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->lock = $this->createMock(LockInterface::class);
        $this->translator = $this->createMock(Translator::class);

        $lockFactory->method('createLock')
            ->with('queue_processor')
            ->willReturn($this->lock);

        $command = new ProcessQueueCommand(
            messageRepository: $this->messageRepository,
            lockFactory: $lockFactory,
            messagePreparator: $this->messageProcessingPreparator,
            messageBus: $this->messageBus,
            configProvider: $this->createMock(ConfigProvider::class),
            translator: $this->translator,
            entityManager: $this->createMock(EntityManagerInterface::class),
        );

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWithLockAlreadyAcquired(): void
    {
        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(false);

        $this->messageProcessingPreparator->expects($this->never())
            ->method('ensureSubscribersHaveUuid');

        $this->translator->expects($this->once())
            ->method('trans')
            ->with('Queue is already being processed by another instance.')
            ->willReturn('Queue is already being processed by another instance.');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Queue is already being processed by another instance.', $output);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithNoCampaigns(): void
    {
        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureSubscribersHaveUuid');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureCampaignsHaveUuid');

        $this->messageRepository->expects($this->once())
            ->method('getByStatusAndEmbargo')
            ->with($this->anything(), $this->anything())
            ->willReturn([]);

        $this->messageBus->expects($this->never())
            ->method('dispatch');

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithCampaigns(): void
    {
        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureSubscribersHaveUuid');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureCampaignsHaveUuid');

        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(1);

        $this->messageRepository->expects($this->once())
            ->method('getByStatusAndEmbargo')
            ->with($this->anything(), $this->anything())
            ->willReturn([$campaign]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (CampaignProcessorMessage $message) use ($campaign) {
                    $this->assertEquals($campaign->getId(), $message->getMessageId());
                    return true;
                }),
                $this->equalTo([])
            )
            ->willReturn(new Envelope(new CampaignProcessorMessage($campaign->getId())));

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithMultipleCampaigns(): void
    {
        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureSubscribersHaveUuid');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureCampaignsHaveUuid');

        $cmp1 = $this->createMock(Message::class);
        $cmp1->method('getId')->willReturn(1);
        $cmp2 = $this->createMock(Message::class);
        $cmp2->method('getId')->willReturn(2);

        $this->messageRepository->expects($this->once())
            ->method('getByStatusAndEmbargo')
            ->with($this->anything(), $this->anything())
            ->willReturn([$cmp1, $cmp2]);

        $this->messageBus->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (CampaignProcessorMessage $message, array $stamps) use ($cmp1, $cmp2) {
                static $call = 0;
                $call++;
                if ($call === 1) {
                    $this->assertEquals($cmp1->getId(), $message->getMessageId());
                } else {
                    $this->assertEquals($cmp2->getId(), $message->getMessageId());
                }
                $this->assertSame([], $stamps);

                return new Envelope(new CampaignProcessorMessage($message->getMessageId()));
            });

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithDispatcherException(): void
    {
        $this->lock->expects($this->once())
            ->method('acquire')
            ->willReturn(true);

        $this->lock->expects($this->once())
            ->method('release');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureSubscribersHaveUuid');

        $this->messageProcessingPreparator->expects($this->once())
            ->method('ensureCampaignsHaveUuid');

        $campaign = $this->createMock(Message::class);
        $campaign->method('getId')->willReturn(1);

        $this->messageRepository->expects($this->once())
            ->method('getByStatusAndEmbargo')
            ->with($this->anything(), $this->anything())
            ->willReturn([$campaign]);

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (CampaignProcessorMessage $message) use ($campaign) {
                    $this->assertEquals($campaign->getId(), $message->getMessageId());
                    return true;
                }),
                $this->equalTo([])
            )
            ->willThrowException(new Exception());

        $this->commandTester->execute([]);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }
}
