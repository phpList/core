<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Command;

use Exception;
use PhpList\Core\Domain\Identity\Command\CleanUpOldSessionTokens;
use PhpList\Core\Domain\Identity\Repository\AdministratorTokenRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CleanUpOldSessionTokensTest extends TestCase
{
    private AdministratorTokenRepository&MockObject $tokenRepository;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->tokenRepository = $this->createMock(AdministratorTokenRepository::class);

        $command = new CleanUpOldSessionTokens($this->tokenRepository);

        $application = new Application();
        $application->add($command);

        $this->commandTester = new CommandTester($command);
    }

    public function testExecuteSuccessfully(): void
    {
        $this->tokenRepository->expects($this->once())
            ->method('removeExpired')
            ->willReturn(5);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Successfully removed 5 expired session token(s)', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithNoExpiredTokens(): void
    {
        $this->tokenRepository->expects($this->once())
            ->method('removeExpired')
            ->willReturn(0);

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Successfully removed 0 expired session token(s)', $output);
        $this->assertEquals(0, $this->commandTester->getStatusCode());
    }

    public function testExecuteWithException(): void
    {
        $this->tokenRepository->expects($this->once())
            ->method('removeExpired')
            ->willThrowException(new Exception('Test exception'));

        $this->commandTester->execute([]);

        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('Error removing expired session tokens: Test exception', $output);
        $this->assertEquals(1, $this->commandTester->getStatusCode());
    }
}
