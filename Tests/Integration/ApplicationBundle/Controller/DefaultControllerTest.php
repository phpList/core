<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\ApplicationBundle\Controller;

use PhpList\PhpList4\Core\Bootstrap;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class DefaultControllerTest extends WebTestCase
{
    /**
     * @var Client
     */
    private $client = null;

    protected function setUp()
    {
        Bootstrap::getInstance()->setApplicationContext(Bootstrap::APPLICATION_CONTEXT_TESTING)->configure();

        $this->client = self::createClient(['environment' => Bootstrap::APPLICATION_CONTEXT_TESTING]);
    }

    /**
     * @test
     */
    public function indexActionReturnsResponseWithHelloWorld()
    {
        $this->client->request('GET', '/');

        self::assertSame(200, $this->client->getResponse()->getStatusCode());
        self::assertContains('Hello world!', $this->client->getResponse()->getContent());
    }
}
