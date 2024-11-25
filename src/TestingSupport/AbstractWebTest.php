<?php

declare(strict_types=1);

namespace PhpList\Core\TestingSupport;

use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for integration tests for controllers.
 *
 * If you have your own setUp method, make sure to call $this->setUpWebTest() first thing in your setUp method.
 */
abstract class AbstractWebTest extends WebTestCase
{
    protected ?KernelBrowser $client = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpWebTest();
    }

    protected function setUpWebTest(): void
    {
        date_default_timezone_set('UTC');

        Bootstrap::getInstance()->setEnvironment(Environment::TESTING)->configure();

        $this->client = static::createClient();
    }
}
