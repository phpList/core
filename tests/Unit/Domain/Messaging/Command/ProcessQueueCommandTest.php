<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Command;

use Exception;
use PhpList\Core\Domain\Messaging\Command\ProcessQueueCommand;
use PhpList\Core\Domain\Messaging\Model\Message;
use PhpList\Core\Domain\Messaging\Repository\MessageRepository;
use PhpList\Core\Domain\Messaging\Service\MessageProcessingPreparator;
use PhpList\Core\Domain\Messaging\Service\Processor\CampaignProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

class ProcessQueueCommandTest extends TestCase
{
    private MessageRepository&MockObject $messageRepository;
    private MessageProcessingPreparator&MockObject $messageProcessingPreparator;
    private CampaignProcessor&MockObject $campaignProcessor;
    private LockInterface&MockObject $lock;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $lockFactory = $this->createMock(LockFactory::class);
        $this->messageProcessingPreparator = $this->createMock(MessageProcessingPreparator::class);
        $this->campaignProcessor = $this->createMock(CampaignProcessor::class);
        $this->lock = $this->createMock(LockInterface::class);

        $lockFactory->method('createLock')
            ->with('queue_processor')
            ->willReturn($this->lock);

        $command = new ProcessQueueCommand(
            $this->messageRepository,
            $lockFactory,
            $this->messageProcessingPreparator,
            $this->campaignProcessor
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

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Queue is already being processed by another instance', $output);
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
            ->method('findBy')
            ->with(['status' => 'submitted'])
            ->willReturn([]);

        $this->campaignProcessor->expects($this->never())
            ->method('process');

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

        $this->messageRepository->expects($this->once())
            ->method('findBy')
            ->with(['status' => 'submitted'])
            ->willReturn([$campaign]);

        $this->campaignProcessor->expects($this->once())
            ->method('process')
            ->with($campaign, $this->anything());

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

        $campaign1 = $this->createMock(Message::class);
        $campaign2 = $this->createMock(Message::class);

        $this->messageRepository->expects($this->once())
            ->method('findBy')
            ->with(['status' => 'submitted'])
            ->willReturn([$campaign1, $campaign2]);

        $this->campaignProcessor->expects($this->exactly(2))
            ->method('process')
            ->withConsecutive(
                [$campaign1, $this->anything()],
                [$campaign2, $this->anything()]
            );

        $this->commandTester->execute([]);

        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithProcessorException(): void
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

        $this->messageRepository->expects($this->once())
            ->method('findBy')
            ->with(['status' => 'submitted'])
            ->willReturn([$campaign]);

        $this->campaignProcessor->expects($this->once())
            ->method('process')
            ->with($campaign, $this->anything())
            ->willThrowException(new Exception('Test exception'));

        $this->commandTester->execute([]);

        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }
}
