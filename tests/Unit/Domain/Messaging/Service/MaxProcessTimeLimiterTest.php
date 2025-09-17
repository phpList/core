<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Messaging\Service;

use PhpList\Core\Domain\Messaging\Service\MaxProcessTimeLimiter;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MaxProcessTimeLimiterTest extends TestCase
{
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testShouldNotStopWhenMaxSecondsIsZero(): void
    {
        $limiter = new MaxProcessTimeLimiter(logger: $this->logger, maxSeconds: 0);

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->never())->method('writeln');
        $this->logger->expects($this->never())->method('warning');

        $limiter->start();
        usleep(200_000);
        $this->assertFalse($limiter->shouldStop($output));
    }

    public function testShouldStopAfterThresholdAndLogAndOutput(): void
    {
        $limiter = new MaxProcessTimeLimiter(logger: $this->logger, maxSeconds: 1);

        $output = $this->createMock(OutputInterface::class);
        $output->expects($this->once())
            ->method('writeln')
            ->with('Reached max processing time; stopping cleanly.');

        $this->logger->expects($this->once())
            ->method('warning')
            ->with($this->stringContains('Reached max processing time of 1 seconds'));

        $this->assertFalse($limiter->shouldStop($output));

        usleep(1_200_000);
        $this->assertTrue($limiter->shouldStop($output));
    }
}
