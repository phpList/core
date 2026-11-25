<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Subscription\Command;

use PhpList\Core\Domain\Subscription\Command\UpdateTldsCommand;
use PhpList\Core\Domain\Subscription\Model\Dto\TldRefreshResult;
use PhpList\Core\Domain\Subscription\Service\TldListService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Contracts\Translation\TranslatorInterface;

class UpdateTldsCommandTest extends TestCase
{
    private TldListService&MockObject $service;
    private CommandTester $tester;

    protected function setUp(): void
    {
        $this->service = $this->createMock(TldListService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $p = []) => strtr($id, $p));
        $this->tester = new CommandTester(new UpdateTldsCommand($this->service, $translator));
    }

    public function testReportsCountOnSuccess(): void
    {
        $this->service->expects($this->once())->method('refresh')->with(false)
            ->willReturn(new TldRefreshResult(1436, TldRefreshResult::SOURCE_REMOTE));

        $this->tester->execute([]);

        $this->assertSame(0, $this->tester->getStatusCode());
        $this->assertStringContainsString('Now we have 1436 top level domains', $this->tester->getDisplay());
    }

    public function testForceOptionIsPassedOn(): void
    {
        $this->service->expects($this->once())->method('refresh')->with(true)
            ->willReturn(new TldRefreshResult(10, TldRefreshResult::SOURCE_SKIPPED));

        $this->tester->execute(['--force' => true]);

        $this->assertSame(0, $this->tester->getStatusCode());
    }

    public function testFailsWhenDownloadFailed(): void
    {
        $this->service->method('refresh')
            ->willReturn(new TldRefreshResult(1400, TldRefreshResult::SOURCE_KEPT));

        $this->tester->execute([]);

        $this->assertSame(1, $this->tester->getStatusCode());
        $this->assertStringContainsString('Could not download', $this->tester->getDisplay());
        $this->assertStringContainsString('1400', $this->tester->getDisplay());
    }
}
