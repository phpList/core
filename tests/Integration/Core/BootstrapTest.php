<?php
declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Core;

use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;
use PHPUnit\Framework\TestCase;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class BootstrapTest extends TestCase
{
    /**
     * @var Bootstrap
     */
    private $subject = null;

    protected function setUp()
    {
        $this->subject = Bootstrap::getInstance();
        $this->subject->setEnvironment(Environment::TESTING);
    }

    protected function tearDown()
    {
        Bootstrap::purgeInstance();
    }

    /**
     * @test
     */
    public function ensureDevelopmentOrTestingEnvironmentForTestingEnvironmentHasFluentInterface()
    {
        static::assertSame($this->subject, $this->subject->ensureDevelopmentOrTestingEnvironment());
    }

    /**
     * @test
     */
    public function getApplicationRootReturnsCoreApplicationRoot()
    {
        static::assertSame(dirname(__DIR__, 3), $this->subject->getApplicationRoot());
    }
}
