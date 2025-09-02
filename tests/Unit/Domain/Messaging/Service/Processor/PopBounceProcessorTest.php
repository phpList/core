<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service\Processor;

use PhpList\Core\Domain\Messaging\Service\BounceProcessingServiceInterface;
use PhpList\Core\Domain\Messaging\Service\Processor\PopBounceProcessor;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PopBounceProcessorTest extends TestCase
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
        $processor = new PopBounceProcessor($this->service, 'mail.example.com', 995, 'INBOX');
        $this->assertSame('pop', $processor->getProtocol());
    }

    public function testProcessWithMultipleMailboxesAndDefaults(): void
    {
        $processor = new PopBounceProcessor($this->service, 'pop.example.com', 110, 'INBOX, ,Custom');

        $this->input->method('getOption')->willReturnMap([
            ['test', true],
            ['maximum', 100],
        ]);

        $this->io->expects($this->exactly(3))->method('section');
        $this->io->expects($this->exactly(3))->method('writeln');

        $this->service->expects($this->exactly(3))
            ->method('processMailbox')
            ->willReturnCallback(function (string $mailbox, int $max, bool $test) {
                $expectedThird = '{pop.example.com:110}Custom';
                $expectedFirst = '{pop.example.com:110}INBOX';
                $this->assertSame(100, $max);
                $this->assertTrue($test);
                if ($mailbox === $expectedFirst) {
                    return 'A';
                }
                if ($mailbox === $expectedThird) {
                    return 'C';
                }
                $this->fail('Unexpected mailbox: ' . $mailbox);
            });

        $result = $processor->process($this->input, $this->io);
        $this->assertSame('AAC', $result);
    }
}
