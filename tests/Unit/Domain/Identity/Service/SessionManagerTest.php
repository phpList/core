<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service;

use PhpList\Core\Domain\Configuration\Service\Manager\EventLogManager;
use PhpList\Core\Domain\Identity\Model\AdministratorToken;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Identity\Repository\AdministratorTokenRepository;
use PhpList\Core\Domain\Identity\Service\Manager\SessionManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SessionManagerTest extends TestCase
{
    public function testCreateSessionWithInvalidCredentialsThrowsExceptionAndLogs(): void
    {
        $adminRepo = $this->createMock(AdministratorRepository::class);
        $adminRepo->expects(self::once())
            ->method('findOneByLoginCredentials')
            ->with('admin', 'wrong')
            ->willReturn(null);

        $tokenRepo = $this->createMock(AdministratorTokenRepository::class);
        $tokenRepo->expects(self::never())->method('save');

        $eventLogManager = $this->createMock(EventLogManager::class);
        $eventLogManager->expects(self::once())
            ->method('log')
            ->with('login', $this->stringContains('admin'));

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::exactly(2))
            ->method('trans')
            ->withConsecutive(
                ["Failed admin login attempt for '%login%'", ['login' => 'admin']],
                ['Not authorized', []]
            )
            ->willReturnOnConsecutiveCalls(
                "Failed admin login attempt for 'admin'",
                'Not authorized'
            );

        $manager = new SessionManager($tokenRepo, $adminRepo, $eventLogManager, $translator);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Not authorized');

        $manager->createSession('admin', 'wrong');
    }

    public function testDeleteSessionCallsRemove(): void
    {
        $token = $this->createMock(AdministratorToken::class);

        $tokenRepo = $this->createMock(AdministratorTokenRepository::class);
        $tokenRepo->expects(self::once())
            ->method('remove')
            ->with($token);

        $adminRepo = $this->createMock(AdministratorRepository::class);
        $eventLogManager = $this->createMock(EventLogManager::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $manager = new SessionManager($tokenRepo, $adminRepo, $eventLogManager, $translator);
        $manager->deleteSession($token);
    }
}
