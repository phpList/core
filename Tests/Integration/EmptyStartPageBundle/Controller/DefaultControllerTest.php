<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\EmptyStartPageBundle\Controller;

use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Core\Environment;
use PhpList\PhpList4\EmptyStartPageBundle\Controller\DefaultController;
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
        Bootstrap::getInstance()->setEnvironment(Environment::TESTING)->configure();

        $this->client = self::createClient(['environment' => Environment::TESTING]);
    }

    /**
     * @test
     */
    public function controllerIsAvailableViaContainer()
    {
        self::assertInstanceOf(DefaultController::class, $this->client->getContainer()->get(DefaultController::class));
    }

    /**
     * @test
     */
    public function indexActionReturnsResponseWithHelloWorld()
    {
        $this->client->request('GET', '/');

        self::assertTrue($this->client->getResponse()->isSuccessful());
        self::assertContains(
            'This page has been intentionally left empty.',
            $this->client->getResponse()->getContent()
        );
    }
}
