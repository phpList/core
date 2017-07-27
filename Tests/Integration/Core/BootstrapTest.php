<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Core;

use PhpList\PhpList4\Core\Bootstrap;
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
        $this->subject->setApplicationContext(Bootstrap::APPLICATION_CONTEXT_TESTING);
    }

    protected function tearDown()
    {
        Bootstrap::purgeInstance();
    }

    /**
     * @test
     */
    public function preventProductionEnvironmentForTestingEnvironmentHasFluentInterface()
    {
        self::assertSame($this->subject, $this->subject->preventProductionEnvironment());
    }

    /**
     * @test
     */
    public function getApplicationRootReturnsCoreApplicationRoot()
    {
        self::assertSame(dirname(__DIR__, 3), $this->subject->getApplicationRoot());
    }
}
