<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Security;

use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Identity\AdministratorToken;
use PhpList\Core\Domain\Repository\Identity\AdministratorTokenRepository;
use PhpList\Core\Security\Authentication;
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
    private AdministratorTokenRepository $tokenRepository;

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

        $token = new AdministratorToken();
        $administrator = new Administrator();
        $administrator->setSuperUser(true);
        $token->setAdministrator($administrator);

        $this->tokenRepository
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

        $token = new AdministratorToken();

        $this->tokenRepository
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
