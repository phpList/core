<?php
declare(strict_types=1);

namespace PhpList\PhpList4\TestingSupport;

use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Core\Environment;
use Symfony\Bundle\FrameworkBundle\Client;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for integration tests for controllers.
 *
 * If you have your own setUp method, make sure to call $this->setUpWebTest() first thing in your setUp method.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
abstract class AbstractWebTest extends WebTestCase
{
    /**
     * @var Client
     */
    protected $client = null;

    protected function setUp()
    {
        $this->setUpWebTest();
    }

    protected function setUpWebTest()
    {
        // This makes sure that all DateTime instances use the same time zone, thus making the dates in the
        // JSON provided by the REST API easier to test.
        date_default_timezone_set('UTC');

        Bootstrap::getInstance()->setEnvironment(Environment::TESTING)->configure();

        $this->client = static::createClient(['environment' => Environment::TESTING]);
    }
}
