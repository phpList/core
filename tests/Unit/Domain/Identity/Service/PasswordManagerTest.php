<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Domain\Identity\Service;

use DateTime;
use PhpList\Core\Domain\Identity\Model\AdminPasswordRequest;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Repository\AdminPasswordRequestRepository;
use PhpList\Core\Domain\Identity\Repository\AdministratorRepository;
use PhpList\Core\Domain\Identity\Service\PasswordManager;
use PhpList\Core\Domain\Messaging\Message\PasswordResetMessage;
use PhpList\Core\Security\HashGenerator;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class PasswordManagerTest extends TestCase
{
    private AdminPasswordRequestRepository&MockObject $passwordRequestRepository;
    private AdministratorRepository&MockObject $administratorRepository;
    private HashGenerator&MockObject $hashGenerator;
    private MessageBusInterface&MockObject $messageBus;
    private PasswordManager $subject;

    protected function setUp(): void
    {
        $this->passwordRequestRepository = $this->createMock(AdminPasswordRequestRepository::class);
        $this->administratorRepository = $this->createMock(AdministratorRepository::class);
        $this->hashGenerator = $this->createMock(HashGenerator::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->subject = new PasswordManager(
            passwordRequestRepository: $this->passwordRequestRepository,
            administratorRepository: $this->administratorRepository,
            hashGenerator: $this->hashGenerator,
            messageBus: $this->messageBus,
            translator: $this->createMock(TranslatorInterface::class)
        );
    }

    public function testGeneratePasswordResetTokenThrowsExceptionIfAdministratorNotFound(): void
    {
        $email = 'nonexistent@example.com';

        $this->administratorRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn(null);

        $this->messageBus
            ->expects($this->never())
            ->method('dispatch');

        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionCode(1500567100);

        $this->subject->generatePasswordResetToken($email);
    }

    public function testGeneratePasswordResetTokenCleansUpExistingRequests(): void
    {
        $email = 'admin@example.com';
        $administrator = new Administrator();
        $administrator->setEmail($email);
        $existingRequest = new AdminPasswordRequest(new DateTime(), $administrator, 'old-token');

        $this->administratorRepository->expects($this->once())
            ->method('findOneBy')
            ->with(['email' => $email])
            ->willReturn($administrator);

        $this->passwordRequestRepository->expects($this->once())
            ->method('findByAdmin')
            ->with($administrator)
            ->willReturn([$existingRequest]);

        $this->passwordRequestRepository->expects($this->once())
            ->method('remove')
            ->with($existingRequest);

        $this->passwordRequestRepository->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(AdminPasswordRequest::class));

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (PasswordResetMessage $emailObj) use ($email) {
                $this->assertEquals($email, $emailObj->getEmail());
                return true;
            }))
            ->willReturn(new Envelope(new PasswordResetMessage($email, 'token')));

        $token = $this->subject->generatePasswordResetToken($email);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function testValidatePasswordResetTokenReturnsNullIfTokenNotFound(): void
    {
        $token = 'nonexistent-token';

        $this->passwordRequestRepository->expects($this->once())
            ->method('findOneByToken')
            ->with($token)
            ->willReturn(null);

        $result = $this->subject->validatePasswordResetToken($token);

        $this->assertNull($result);
    }

    public function testValidatePasswordResetTokenReturnsNullIfTokenExpired(): void
    {
        $token = 'expired-token';
        $administrator = new Administrator();
        $expiredDate = new DateTime('-1 day');
        $passwordRequest = new AdminPasswordRequest($expiredDate, $administrator, $token);

        $this->passwordRequestRepository->expects($this->once())
            ->method('findOneByToken')
            ->with($token)
            ->willReturn($passwordRequest);

        $this->passwordRequestRepository->expects($this->once())
            ->method('remove')
            ->with($passwordRequest);

        $result = $this->subject->validatePasswordResetToken($token);

        $this->assertNull($result);
    }

    public function testValidatePasswordResetTokenReturnsAdministratorIfTokenValid(): void
    {
        $token = 'valid-token';
        $administrator = new Administrator();
        $futureDate = new DateTime('+1 day');
        $passwordRequest = new AdminPasswordRequest($futureDate, $administrator, $token);

        $this->passwordRequestRepository->expects($this->once())
            ->method('findOneByToken')
            ->with($token)
            ->willReturn($passwordRequest);

        $result = $this->subject->validatePasswordResetToken($token);

        $this->assertSame($administrator, $result);
    }

    public function testUpdatePasswordWithTokenReturnsFalseIfTokenInvalid(): void
    {
        $token = 'invalid-token';
        $newPassword = 'new-password';

        $this->passwordRequestRepository->expects($this->once())
            ->method('findOneByToken')
            ->with($token)
            ->willReturn(null);

        $result = $this->subject->updatePasswordWithToken($token, $newPassword);

        $this->assertFalse($result);
    }

    public function testUpdatePasswordWithTokenUpdatesPasswordAndRemovesToken(): void
    {
        $token = 'valid-token';
        $newPassword = 'new-password';
        $newPasswordHash = 'new-password-hash';
        $administrator = new Administrator();
        $futureDate = new DateTime('+1 day');
        $passwordRequest = new AdminPasswordRequest($futureDate, $administrator, $token);

        $this->passwordRequestRepository->expects($this->exactly(2))
            ->method('findOneByToken')
            ->with($token)
            ->willReturn($passwordRequest);

        $this->hashGenerator->expects($this->once())
            ->method('createPasswordHash')
            ->with($newPassword)
            ->willReturn($newPasswordHash);

        $this->administratorRepository->expects($this->once())
            ->method('persist')
            ->with($administrator);

        $this->passwordRequestRepository->expects($this->once())
            ->method('remove')
            ->with($passwordRequest);

        $result = $this->subject->updatePasswordWithToken($token, $newPassword);

        $this->assertTrue($result);
        $this->assertEquals($newPasswordHash, $administrator->getPasswordHash());
    }

    public function testCleanupExpiredTokensRemovesExpiredTokens(): void
    {
        $administrator = new Administrator();
        $expiredRequest1 = new AdminPasswordRequest(new DateTime('-1 day'), $administrator, 'token1');
        $expiredRequest2 = new AdminPasswordRequest(new DateTime('-2 days'), $administrator, 'token2');
        $validRequest = new AdminPasswordRequest(new DateTime('+1 day'), $administrator, 'token3');

        $this->passwordRequestRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$expiredRequest1, $expiredRequest2, $validRequest]);

        $this->passwordRequestRepository->expects($this->exactly(2))
            ->method('remove')
            ->withConsecutive([$expiredRequest1], [$expiredRequest2]);

        $this->subject->cleanupExpiredTokens();
    }
}
