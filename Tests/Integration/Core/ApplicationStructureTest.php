<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Core;

use PhpList\PhpList4\Core\ApplicationKernel;
use PhpList\PhpList4\Core\ApplicationStructure;
use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Core\Environment;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ApplicationStructureTest extends TestCase
{
    /**
     * @var ApplicationKernel
     */
    private $kernel = null;

    /**
     * @var ContainerInterface
     */
    private $container = null;

    protected function setUp()
    {
        $bootstrap = Bootstrap::getInstance();
        $bootstrap->setEnvironment(Environment::TESTING)->configure();

        $this->kernel = $bootstrap->getApplicationKernel();
        $this->kernel->boot();

        $this->container = $this->kernel->getContainer();
    }

    protected function tearDown()
    {
        $this->kernel->shutdown();
        Bootstrap::purgeInstance();
    }

    /**
     * @test
     */
    public function subjectIsAvailableViaContainer()
    {
        self::assertInstanceOf(ApplicationStructure::class, $this->container->get(ApplicationStructure::class));
    }

    /**
     * @test
     */
    public function classIsRegisteredAsSingletonInContainer()
    {
        $id = ApplicationStructure::class;

        self::assertSame($this->container->get($id), $this->container->get($id));
    }
}
