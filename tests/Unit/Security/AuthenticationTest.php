<?php
declare(strict_types=1);

namespace PhpList\Core\Tests\Unit\Security;

use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Domain\Model\Identity\AdministratorToken;
use PhpList\Core\Domain\Repository\Identity\AdministratorTokenRepository;
use PhpList\Core\Security\Authentication;
use PHPUnit\Framework\TestCase;
use Prophecy\Prophecy\ObjectProphecy;
use Prophecy\Prophecy\ProphecySubjectInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AuthenticationTest extends TestCase
{
    /**
     * @var Authentication
     */
    private $subject = null;

    /**
     * @var AdministratorTokenRepository|ObjectProphecy
     */
    private $tokenRepositoryProphecy = null;

    protected function setUp()
    {
        $this->tokenRepositoryProphecy = $this->prophesize(AdministratorTokenRepository::class);
        /** @var AdministratorTokenRepository|ProphecySubjectInterface $tokenRepository */
        $tokenRepository = $this->tokenRepositoryProphecy->reveal();
        $this->subject = new Authentication($tokenRepository);
    }

    /**
     * @test
     */
    public function authenticateByApiKeyWithValidApiKeyInBasicAuthReturnsMatchingAdministrator()
    {
        $apiKey = 'biuzaswcefblkjuzq43wtw2413';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $token = new AdministratorToken();
        $administrator = new Administrator();
        $administrator->setSuperUser(true);
        $token->setAdministrator($administrator);

        $this->tokenRepositoryProphecy->findOneUnexpiredByKey($apiKey)->willReturn($token)->shouldBeCalled();

        static::assertSame($administrator, $this->subject->authenticateByApiKey($request));
    }

    /**
     * @test
     */
    public function authenticateByApiKeyWithValidApiKeyInBasicAuthWithoutAdministratorReturnsNull()
    {
        $apiKey = 'biuzaswcefblkjuzq43wtw2413';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $token = new AdministratorToken();

        $this->tokenRepositoryProphecy->findOneUnexpiredByKey($apiKey)->willReturn($token)->shouldBeCalled();

        static::assertNull($this->subject->authenticateByApiKey($request));
    }

    /**
     * @test
     */
    public function authenticateByApiKeyWithInvalidApiKeyInBasicAuthReturnsNull()
    {
        $apiKey = 'biuzaswcefblkjuzq43wtw2413';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $this->tokenRepositoryProphecy->findOneUnexpiredByKey($apiKey)->willReturn(null)->shouldBeCalled();

        static::assertNull($this->subject->authenticateByApiKey($request));
    }

    /**
     * @test
     */
    public function authenticateByApiKeyWithEmptyApiKeyInBasicAuthReturnsNull()
    {
        $request = new Request();
        $request->headers->add(['php-auth-pw' => '']);

        static::assertNull($this->subject->authenticateByApiKey($request));
    }

    /**
     * @test
     */
    public function authenticateByApiKeyWithMissingApiKeyInBasicAuthReturnsNull()
    {
        $request = new Request();

        static::assertNull($this->subject->authenticateByApiKey($request));
    }
}
