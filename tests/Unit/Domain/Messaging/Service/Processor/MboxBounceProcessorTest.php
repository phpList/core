<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Processor;

use PhpList\Core\Domain\Messaging\Service\BounceProcessingServiceInterface;
use PhpList\Core\Domain\Messaging\Service\Processor\MboxBounceProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Translation\Translator;

class MboxBounceProcessorTest extends TestCase
{
    private BounceProcessingServiceInterface&MockObject $service;
    private InputInterface&MockObject $input;
    private SymfonyStyle&MockObject $io;

    protected function setUp(): void
    {
        $this->service = $this->createMock(BounceProcessingServiceInterface::class);
        $this->input = $this->createMock(InputInterface::class);
        $this->io = $this->createMock(SymfonyStyle::class);
    }

    public function testGetProtocol(): void
    {
        $processor = new MboxBounceProcessor($this->service, new Translator('en'));
        $this->assertSame('mbox', $processor->getProtocol());
    }

    public function testProcessThrowsWhenMailboxMissing(): void
    {
        $translator = new Translator('en');
        $processor = new MboxBounceProcessor($this->service, $translator);

        $this->input->method('getOption')->willReturnMap([
            ['test', false],
            ['maximum', 0],
            ['mailbox', ''],
        ]);

        $this->io
            ->expects($this->once())
            ->method('error')
            ->with($translator->trans('mbox file path must be provided with --mailbox.'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing --mailbox for mbox protocol');

        $processor->process($this->input, $this->io);
    }

    public function testProcessSuccess(): void
    {
        $translator = new Translator('en');
        $processor = new MboxBounceProcessor($this->service, $translator);

        $this->input->method('getOption')->willReturnMap([
            ['test', true],
            ['maximum', 50],
            ['mailbox', '/var/mail/bounce.mbox'],
        ]);

        $this->io->expects($this->once())->method('section')->with($translator->trans('Opening mbox %file%', ['%file%' => '/var/mail/bounce.mbox']));
        $this->io->expects($this->once())->method('writeln')->with($translator->trans('Please do not interrupt this process'));

        $this->service->expects($this->once())
            ->method('processMailbox')
            ->with('/var/mail/bounce.mbox', 50, true)
            ->willReturn('OK');

        $result = $processor->process($this->input, $this->io);
        $this->assertSame('OK', $result);
    }
}
