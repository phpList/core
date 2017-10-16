<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Core;

use Doctrine\Bundle\DoctrineBundle\Registry as DoctrineRegistry;
use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Core\Environment;
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
        self::assertSame($this->subject, $this->subject->ensureDevelopmentOrTestingEnvironment());
    }

    /**
     * @test
     */
    public function getApplicationRootReturnsCoreApplicationRoot()
    {
        self::assertSame(dirname(__DIR__, 3), $this->subject->getApplicationRoot());
    }

    /**
     * @test
     */
    public function getDoctrineAfterConfigureReturnsDoctrineRegistry()
    {
        $this->subject->configure();

        self::assertInstanceOf(DoctrineRegistry::class, $this->subject->getDoctrine());
    }
}
