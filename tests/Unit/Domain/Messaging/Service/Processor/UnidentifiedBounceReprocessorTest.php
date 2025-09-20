<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Processor;

use DateTimeImmutable;
use PhpList\Core\Domain\Messaging\Model\Bounce;
use PhpList\Core\Domain\Messaging\Service\Manager\BounceManager;
use PhpList\Core\Domain\Messaging\Service\MessageParser;
use PhpList\Core\Domain\Messaging\Service\Processor\BounceDataProcessor;
use PhpList\Core\Domain\Messaging\Service\Processor\UnidentifiedBounceReprocessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Translator;

class UnidentifiedBounceReprocessorTest extends TestCase
{
    private BounceManager&MockObject $bounceManager;
    private MessageParser&MockObject $messageParser;
    private BounceDataProcessor&MockObject $dataProcessor;
    private SymfonyStyle&MockObject $io;

    protected function setUp(): void
    {
        $this->bounceManager = $this->createMock(BounceManager::class);
        $this->messageParser = $this->createMock(MessageParser::class);
        $this->dataProcessor = $this->createMock(BounceDataProcessor::class);
        $this->io = $this->createMock(SymfonyStyle::class);
    }

    public function testProcess(): void
    {
        $bounce1 = $this->createBounce('H1', 'D1');
        $bounce2 = $this->createBounce('H2', 'D2');
        $bounce3 = $this->createBounce('H3', 'D3');
        $this->bounceManager
            ->method('findByStatus')
            ->with('unidentified bounce')
            ->willReturn([$bounce1, $bounce2, $bounce3]);

        $this->io->expects($this->once())->method('section')->with('Reprocessing unidentified bounces');
        $this->io->expects($this->exactly(3))->method('writeln');

        // For b1: only userId found -> should process
        $this->messageParser->expects($this->exactly(3))->method('decodeBody');
        $this->messageParser->method('findUserId')->willReturnOnConsecutiveCalls(111, null, 222);
        $this->messageParser->method('findMessageId')->willReturnOnConsecutiveCalls(null, '555', '666');

        // process called for b1 and b3 (two calls return true and true),
        // and also for b2 since it has messageId -> should be called too -> total 3 calls
        $this->dataProcessor->expects($this->exactly(3))
            ->method('process')
            ->with(
                $this->anything(),
                $this->callback(fn($messageId) => $messageId === null || is_string($messageId)),
                $this->callback(fn($messageId) => $messageId === null || is_int($messageId)),
                $this->isInstanceOf(DateTimeImmutable::class)
            )
            ->willReturnOnConsecutiveCalls(true, false, true);

        $processor = new UnidentifiedBounceReprocessor(
            bounceManager: $this->bounceManager,
            messageParser: $this->messageParser,
            bounceDataProcessor: $this->dataProcessor,
            translator: new Translator('en'),
        );
        $processor->process($this->io);
    }

    private function createBounce(string $header, string $data): Bounce
    {
        // Bounce constructor: (DateTime|null, header, data, status, comment)
        return new Bounce(null, $header, $data, null, null);
    }
}
