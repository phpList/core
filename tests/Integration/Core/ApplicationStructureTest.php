<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Core;

use PhpList\Core\Core\ApplicationKernel;
use PhpList\Core\Core\ApplicationStructure;
use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;
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
    private ApplicationKernel $kernel;

    /**
     * @var ContainerInterface
     */
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $bootstrap = Bootstrap::getInstance();
        $bootstrap->setEnvironment(Environment::TESTING)->configure();

        $this->kernel = $bootstrap->getApplicationKernel();
        $this->kernel->boot();

        $this->container = $this->kernel->getContainer();
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        Bootstrap::purgeInstance();
    }

    /**
     * @test
     */
    public function subjectIsAvailableViaContainer()
    {
        static::assertInstanceOf(ApplicationStructure::class, $this->container->get(ApplicationStructure::class));
    }

    /**
     * @test
     */
    public function classIsRegisteredAsSingletonInContainer()
    {
        $id = ApplicationStructure::class;

        static::assertSame($this->container->get($id), $this->container->get($id));
    }
}
