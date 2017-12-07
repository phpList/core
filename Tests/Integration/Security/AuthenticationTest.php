<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Security;

use PhpList\PhpList4\Domain\Model\Identity\Administrator;
use PhpList\PhpList4\Security\Authentication;
use PhpList\PhpList4\Tests\Integration\AbstractDatabaseTest;
use Symfony\Component\HttpFoundation\Request;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class AuthenticationTest extends AbstractDatabaseTest
{
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
        parent::setUp();

        $this->subject = $this->container->get(Authentication::class);
    }

    /**
     * @test
     */
    public function subjectIsAvailableViaContainer()
    {
        self::assertInstanceOf(Authentication::class, $this->subject);
    }

    /**
     * @test
     */
    public function classIsRegisteredAsSingletonInContainer()
    {
        self::assertSame($this->subject, $this->container->get(Authentication::class));
    }

    /**
     * @test
     */
    public function authenticateByApiKeyWithValidApiKeyReturnsMatchingAdministrator()
    {
        $this->getDataSet()->addTable(
            self::ADMINISTRATOR_TABLE_NAME,
            __DIR__ . '/../Domain/Repository/Identity/Fixtures/Administrator.csv'
        );
        $this->getDataSet()->addTable(
            self::TOKEN_TABLE_NAME,
            __DIR__ . '/../Domain/Repository/Identity/Fixtures/AdministratorTokenWithAdministrator.csv'
        );
        $this->applyDatabaseChanges();

        $apiKey = 'cfdf64eecbbf336628b0f3071adba762';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $result = $this->subject->authenticateByApiKey($request);

        self::assertInstanceOf(Administrator::class, $result);
        self::assertSame(1, $result->getId());
    }

    /**
     * @test
     */
    public function authenticateByApiKeyWithValidApiKeyForInexistentAdministratorReturnsNull()
    {
        $this->getDataSet()->addTable(
            self::TOKEN_TABLE_NAME,
            __DIR__ . '/../Domain/Repository/Identity/Fixtures/AdministratorTokenWithAdministrator.csv'
        );
        $this->applyDatabaseChanges();

        $apiKey = 'cfdf64eecbbf336628b0f3071adba763';
        $request = new Request();
        $request->headers->add(['php-auth-pw' => $apiKey]);

        $result = $this->subject->authenticateByApiKey($request);

        self::assertNull($result);
    }
}
