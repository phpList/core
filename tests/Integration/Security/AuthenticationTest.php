<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Security;

use PhpList\Core\Domain\Model\Identity\Administrator;
use PhpList\Core\Security\Authentication;
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

        $this->subject = $this->container->get(Authentication::class);
    }

    /**
     * @test
     */
    public function subjectIsAvailableViaContainer()
    {
        static::assertInstanceOf(Authentication::class, $this->subject);
    }

    /**
     * @test
     */
    public function classIsRegisteredAsSingletonInContainer()
    {
        static::assertSame($this->subject, $this->container->get(Authentication::class));
    }

    /**
     * @test
     */
    public function authenticateByApiKeyWithValidApiKeyReturnsMatchingAdministrator()
    {
        $this->getDataSet()->addTable(
            static::ADMINISTRATOR_TABLE_NAME,
            __DIR__ . '/../Domain/Repository/Fixtures/Administrator.csv'
        );
        $this->getDataSet()->addTable(
            static::TOKEN_TABLE_NAME,
            __DIR__ . '/../Domain/Repository/Fixtures/AdministratorTokenWithAdministrator.csv'
        );
        $this->applyDatabaseChanges();

        $apiKey = 'cfdf64eecbbf336628b0f3071adba762';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $result = $this->subject->authenticateByApiKey($request);

        static::assertInstanceOf(Administrator::class, $result);
        static::assertSame(1, $result->getId());
    }

    /**
     * @test
     */
    public function authenticateByApiKeyWithValidApiKeyAndDisabledAdministratorReturnsNull()
    {
        $this->getDataSet()->addTable(
            static::ADMINISTRATOR_TABLE_NAME,
            __DIR__ . '/../Domain/Repository/Fixtures/Administrator.csv'
        );
        $this->getDataSet()->addTable(
            static::TOKEN_TABLE_NAME,
            __DIR__ . '/../Domain/Repository/Fixtures/AdministratorTokenWithAdministrator.csv'
        );
        $this->applyDatabaseChanges();

        $apiKey = 'cfdf64eecbbf336628b0f3071adba765';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $result = $this->subject->authenticateByApiKey($request);

        static::assertNull($result);
    }

    /**
     * @test
     */
    public function authenticateByApiKeyWithValidApiKeyForInexistentAdministratorReturnsNull()
    {
        $this->getDataSet()->addTable(
            static::TOKEN_TABLE_NAME,
            __DIR__ . '/../Domain/Repository/Fixtures/AdministratorTokenWithAdministrator.csv'
        );
        $this->applyDatabaseChanges();

        $apiKey = 'cfdf64eecbbf336628b0f3071adba763';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $result = $this->subject->authenticateByApiKey($request);

        static::assertNull($result);
    }

    /**
     * @test
     */
    public function authenticateByApiKeyWithValidApiKeyForNonSuperUserAdministratorReturnsNull()
    {
        $this->getDataSet()->addTable(
            static::TOKEN_TABLE_NAME,
            __DIR__ . '/../Domain/Repository/Fixtures/AdministratorTokenWithAdministrator.csv'
        );
        $this->applyDatabaseChanges();

        $apiKey = 'cfdf64eecbbf336628b0f3071adba764';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $result = $this->subject->authenticateByApiKey($request);

        static::assertNull($result);
    }
}
