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

        $this->client = static::createClient(['environment' => Environment::TESTING]);
    }

    /**
     * @test
     */
    public function controllerIsAvailableViaContainer()
    {
        static::assertInstanceOf(
            DefaultController::class,
            $this->client->getContainer()->get(DefaultController::class)
        );
    }

    /**
     * @test
     */
    public function indexActionReturnsResponseWithHelloWorld()
    {
        $this->client->request('GET', '/');

        static::assertTrue($this->client->getResponse()->isSuccessful());
        static::assertContains(
            'This page has been intentionally left empty.',
            $this->client->getResponse()->getContent()
        );
    }
}
