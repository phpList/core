<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Security;

use PhpList\Core\Domain\Identity\Repository\AdministratorTokenRepository;
use PhpList\Core\Domain\Identity\Model\Administrator;
use PhpList\Core\Domain\Identity\Model\AdministratorToken;
use PhpList\Core\Security\Authentication;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AuthenticationTest extends TestCase
{
    private Authentication $subject;
    private AdministratorTokenRepository|MockObject $tokenRepository;

    protected function setUp(): void
    {
        $this->tokenRepository = $this->createMock(AdministratorTokenRepository::class);
        $this->subject = new Authentication($this->tokenRepository);
    }

    public function testAuthenticateByApiKeyWithValidApiKeyInBasicAuthReturnsMatchingAdministrator(): void
    {
        $apiKey = 'biuzaswcefblkjuzq43wtw2413';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $administrator = new Administrator();
        $administrator->setSuperUser(true);
        $token = new AdministratorToken($administrator);

        $this->tokenRepository
            ->expects($this->any())
            ->method('findOneUnexpiredByKey')
            ->with($apiKey)
            ->willReturn($token);

        self::assertSame($administrator, $this->subject->authenticateByApiKey($request));
    }

    public function testAuthenticateByApiKeyWithValidApiKeyInBasicAuthWithoutAdministratorReturnsNull(): void
    {
        $apiKey = 'biuzaswcefblkjuzq43wtw2413';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $token = $this->createMock(AdministratorToken::class);

        $this->tokenRepository
            ->expects($this->any())
            ->method('findOneUnexpiredByKey')
            ->with($apiKey)
            ->willReturn($token);

        self::assertNull($this->subject->authenticateByApiKey($request));
    }

    public function testAuthenticateByApiKeyWithInvalidApiKeyInBasicAuthReturnsNull(): void
    {
        $apiKey = 'biuzaswcefblkjuzq43wtw2413';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $this->tokenRepository
            ->expects($this->any())
            ->method('findOneUnexpiredByKey')
            ->with($apiKey)
            ->willReturn(null);

        self::assertNull($this->subject->authenticateByApiKey($request));
    }

    public function testAuthenticateByApiKeyWithEmptyApiKeyInBasicAuthReturnsNull(): void
    {
        $request = new Request();
        $request->headers->add(['php-auth-pw' => '']);

        self::assertNull($this->subject->authenticateByApiKey($request));
    }

    public function testAuthenticateByApiKeyWithMissingApiKeyInBasicAuthReturnsNull(): void
    {
        $request = new Request();

        self::assertNull($this->subject->authenticateByApiKey($request));
    }
}
