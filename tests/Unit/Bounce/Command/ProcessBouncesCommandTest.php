<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Bounce\Command;

use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PhpList\Core\Bounce\Command\ProcessBouncesCommand;
use PhpList\Core\Bounce\Service\ConsecutiveBounceHandler;
use PhpList\Core\Bounce\Service\LockService;
use PhpList\Core\Bounce\Service\Processor\AdvancedBounceRulesProcessor;
use PhpList\Core\Bounce\Service\Processor\BounceProtocolProcessor;
use PhpList\Core\Bounce\Service\Processor\UnidentifiedBounceReprocessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

class ProcessBouncesCommandTest extends TestCase
{
    private LockService&MockObject $lockService;
    private LoggerInterface&MockObject $logger;
    private BounceProtocolProcessor&MockObject $protocolProcessor;
    private AdvancedBounceRulesProcessor&MockObject $advancedRulesProcessor;
    private UnidentifiedBounceReprocessor&MockObject $unidentifiedReprocessor;
    private ConsecutiveBounceHandler&MockObject $consecutiveBounceHandler;

    private CommandTester $commandTester;
    private TranslatorInterface|MockObject $translator;

    protected function setUp(): void
    {
        $this->lockService = $this->createMock(LockService::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->protocolProcessor = $this->createMock(BounceProtocolProcessor::class);
        $this->advancedRulesProcessor = $this->createMock(AdvancedBounceRulesProcessor::class);
        $this->unidentifiedReprocessor = $this->createMock(UnidentifiedBounceReprocessor::class);
        $this->consecutiveBounceHandler = $this->createMock(ConsecutiveBounceHandler::class);
        $this->translator = new Translator('en');

        $command = new ProcessBouncesCommand(
            lockService: $this->lockService,
            logger: $this->logger,
            protocolProcessors: [$this->protocolProcessor],
            advancedRulesProcessor: $this->advancedRulesProcessor,
            unidentifiedReprocessor: $this->unidentifiedReprocessor,
            consecutiveBounceHandler: $this->consecutiveBounceHandler,
            translator: $this->translator,
            entityManager: $this->createMock(EntityManagerInterface::class),
        );

        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteWhenLockNotAcquired(): void
    {
        $this->lockService->expects($this->once())
            ->method('acquirePageLock')
            ->with('bounce_processor', false)
            ->willReturn(null);

        $this->protocolProcessor->expects($this->never())->method('getProtocol');
        $this->protocolProcessor->expects($this->never())->method('process');
        $this->unidentifiedReprocessor->expects($this->never())->method('process');
        $this->advancedRulesProcessor->expects($this->never())->method('process');
        $this->consecutiveBounceHandler->expects($this->never())->method('handle');

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Another bounce processing is already running. Aborting.', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithUnsupportedProtocol(): void
    {
        $this->lockService
            ->expects($this->once())
            ->method('acquirePageLock')
            ->with('bounce_processor', false)
            ->willReturn(123);
        $this->lockService
            ->expects($this->once())
            ->method('release')
            ->with(123);

        $this->protocolProcessor->method('getProtocol')->willReturn('pop');
        $this->protocolProcessor->expects($this->never())->method('process');

        $this->commandTester->execute([
            '--protocol' => 'mbox',
        ]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Unsupported protocol: mbox', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testSuccessfulProcessingFlow(): void
    {
        $this->lockService
            ->expects($this->once())
            ->method('acquirePageLock')
            ->with('bounce_processor', false)
            ->willReturn(456);
        $this->lockService
            ->expects($this->once())
            ->method('release')
            ->with(456);

        $this->protocolProcessor->method('getProtocol')->willReturn('pop');
        $this->protocolProcessor
            ->expects($this->once())
            ->method('process')
            ->with(
                $this->callback(function ($input) {
                    return $input->getOption('protocol') === 'pop'
                        && $input->getOption('test') === false
                        && $input->getOption('purge-unprocessed') === false;
                }),
                $this->anything()
            )
            ->willReturn('downloaded 10 messages');

        $this->unidentifiedReprocessor
            ->expects($this->once())
            ->method('process')
            ->with($this->anything());

        $this->advancedRulesProcessor
            ->expects($this->once())
            ->method('process')
            ->with($this->anything(), 1000);

        $this->consecutiveBounceHandler
            ->expects($this->once())
            ->method('handle')
            ->with($this->anything());

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Bounce processing completed', $this->arrayHasKey('downloadReport'));

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Bounce processing completed.', $output);
        $this->assertSame(0, $this->commandTester->getStatusCode());
    }

    public function testProcessingFlowWhenProcessorThrowsException(): void
    {
        $this->lockService
            ->expects($this->once())
            ->method('acquirePageLock')
            ->with('bounce_processor', false)
            ->willReturn(42);
        $this->lockService
            ->expects($this->once())
            ->method('release')
            ->with(42);

        $this->protocolProcessor->method('getProtocol')->willReturn('pop');

        $this->protocolProcessor
            ->expects($this->once())
            ->method('process')
            ->willThrowException(new Exception('boom'));

        $this->unidentifiedReprocessor->expects($this->never())->method('process');
        $this->advancedRulesProcessor->expects($this->never())->method('process');
        $this->consecutiveBounceHandler->expects($this->never())->method('handle');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Bounce processing failed', $this->arrayHasKey('exception'));

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Error: boom', $output);
        $this->assertSame(1, $this->commandTester->getStatusCode());
    }

    public function testForceOptionIsPassedToLockService(): void
    {
        $this->lockService->expects($this->once())
            ->method('acquirePageLock')
            ->with('bounce_processor', true)
            ->willReturn(1);
        $this->protocolProcessor->method('getProtocol')->willReturn('pop');

        $this->commandTester->execute([
            '--force' => true,
        ]);

        $this->assertSame(0, $this->commandTester->getStatusCode());
    }
}
