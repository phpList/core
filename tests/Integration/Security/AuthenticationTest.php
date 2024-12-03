<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Security;

use Doctrine\ORM\Tools\SchemaTool;
use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Security\Authentication;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\AdministratorFixture;
use PhpList\Core\Tests\Integration\Domain\Repository\Fixtures\AdministratorTokenWithAdministratorFixture;
use PhpList\Core\Tests\TestingSupport\Traits\DatabaseTestTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AuthenticationTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private ?Authentication $subject = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->loadSchema();

        $this->subject = self::getContainer()->get(Authentication::class);
    }

    protected function tearDown(): void
    {
        $schemaTool = new SchemaTool($this->entityManager);
        $schemaTool->dropDatabase();
        parent::tearDown();
    }

    public function testSubjectIsAvailableViaContainer()
    {
        self::assertInstanceOf(Authentication::class, $this->subject);
    }

    public function testClassIsRegisteredAsSingletonInContainer()
    {
        self::assertSame($this->subject, self::getContainer()->get(Authentication::class));
    }

    public function testAuthenticateByApiKeyWithValidApiKeyReturnsMatchingAdministrator()
    {
        $this->loadFixtures([AdministratorFixture::class, AdministratorTokenWithAdministratorFixture::class]);

        $apiKey = 'cfdf64eecbbf336628b0f3071adba762';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $result = $this->subject->authenticateByApiKey($request);

        self::assertInstanceOf(Administrator::class, $result);
        self::assertSame(1, $result->getId());
    }

    public function testAuthenticateByApiKeyWithValidApiKeyAndDisabledAdministratorReturnsNull()
    {
        $this->loadFixtures([AdministratorFixture::class, AdministratorTokenWithAdministratorFixture::class]);

        $apiKey = 'cfdf64eecbbf336628b0f3071adba765';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $result = $this->subject->authenticateByApiKey($request);

        self::assertNull($result);
    }

    public function testAuthenticateByApiKeyWithValidApiKeyForInexistentAdministratorReturnsNull()
    {
        $this->loadFixtures([AdministratorTokenWithAdministratorFixture::class]);

        $apiKey = 'cfdf64eecbbf336628b0f3071adba763';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $result = $this->subject->authenticateByApiKey($request);

        static::assertNull($result);
    }

    public function testAuthenticateByApiKeyWithValidApiKeyForNonSuperUserAdministratorReturnsNull()
    {
        $this->loadFixtures([AdministratorTokenWithAdministratorFixture::class]);

        $apiKey = 'cfdf64eecbbf336628b0f3071adba764';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $result = $this->subject->authenticateByApiKey($request);

        self::assertNull($result);
    }
}
