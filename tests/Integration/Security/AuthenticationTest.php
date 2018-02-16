<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Security;

use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Security\Authentication;
use PhpList\PhpList4\TestingSupport\Traits\DatabaseTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AuthenticationTest extends TestCase
{
    use DatabaseTestTrait;

    /**
     * @var string
     */
    const ADMINISTRATOR_TABLE_NAME = 'phplist_admin';

    /**
     * @var string
     */
    const TOKEN_TABLE_NAME = 'phplist_admintoken';

    /**
     * @var Authentication
     */
    private $subject = null;

    protected function setUp()
    {
        $this->setUpDatabaseTest();

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
