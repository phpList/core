<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Service\Manager;

use PhpList\Core\Domain\Model\Identity\AdministratorToken;
use PhpList\Core\Domain\Repository\Identity\AdministratorRepository;
use PhpList\Core\Domain\Repository\Identity\AdministratorTokenRepository;
use PhpList\Core\Domain\Service\Manager\SessionManager;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class SessionManagerTest extends TestCase
{
    public function testCreateSessionWithInvalidCredentialsThrowsException(): void
    {
        $adminRepo = $this->createMock(AdministratorRepository::class);
        $adminRepo->expects(self::once())
            ->method('findOneByLoginCredentials')
            ->with('admin', 'wrong')
            ->willReturn(null);

        $tokenRepo = $this->createMock(AdministratorTokenRepository::class);
        $tokenRepo->expects(self::never())->method('save');

        $manager = new SessionManager($tokenRepo, $adminRepo);

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

        $manager = new SessionManager($tokenRepo, $adminRepo);
        $manager->deleteSession($token);
    }
}
