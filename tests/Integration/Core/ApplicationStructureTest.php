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
    private ApplicationKernel $kernel;
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

    public function testSubjectIsAvailableViaContainer()
    {
        self::assertInstanceOf(ApplicationStructure::class, $this->container->get(ApplicationStructure::class));
    }

    public function testClassIsRegisteredAsSingletonInContainer()
    {
        $id = ApplicationStructure::class;

        self::assertSame($this->container->get($id), $this->container->get($id));
    }
}
