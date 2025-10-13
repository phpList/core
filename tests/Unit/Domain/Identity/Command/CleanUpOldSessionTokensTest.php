<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Command;

use Doctrine\ORM\EntityManagerInterface;
use PhpList\Core\Domain\Identity\Command\CleanUpOldSessionTokens;
use PhpList\Core\Domain\Identity\Repository\AdministratorTokenRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CleanUpOldSessionTokensTest extends TestCase
{
    public function testItRemovesAllExpiredTokensAndOutputsSuccess(): void
    {
        $repo = $this->createMock(AdministratorTokenRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $token1 = (object) ['id' => 1];
        $token2 = (object) ['id' => 2];
        $expired = [$token1, $token2];

        $repo->expects($this->once())
            ->method('getExpired')
            ->willReturn($expired);

        $removed = [];
        $em->expects($this->exactly(\count($expired)))
            ->method('remove')
            ->willReturnCallback(function (object $o) use (&$removed) {
                $removed[] = $o;
            });

        $em->expects($this->once())
            ->method('flush');

        $command = new CleanUpOldSessionTokens($repo, $em);
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $display = $tester->getDisplay();
        self::assertStringContainsString('Successfully removed 2 expired session token(s).', $display);

        self::assertEqualsCanonicalizing($expired, $removed);
    }

    public function testItHandlesExceptionsAndOutputsFailure(): void
    {
        $repo = $this->createMock(AdministratorTokenRepository::class);
        $em = $this->createMock(EntityManagerInterface::class);

        $repo->expects($this->once())
            ->method('getExpired')
            ->willThrowException(new \RuntimeException('boom'));

        $em->expects($this->never())->method('remove');
        $em->expects($this->never())->method('flush');

        $command = new CleanUpOldSessionTokens($repo, $em);
        $tester  = new CommandTester($command);

        $exitCode = $tester->execute([]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('Error removing expired session tokens: boom', $tester->getDisplay());
    }
}
