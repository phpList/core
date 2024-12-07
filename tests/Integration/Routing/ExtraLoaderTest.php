<?php

declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Routing;

use PhpList\Core\Core\ApplicationKernel;
use PhpList\Core\Core\ApplicationStructure;
use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;
use PhpList\Core\Routing\ExtraLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Loader\AttributeDirectoryLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ExtraLoaderTest extends TestCase
{
    private ?ExtraLoader $subject = null;
    private ?ApplicationKernel $kernel = null;

    protected function setUp(): void
    {
        $bootstrap = Bootstrap::getInstance();
        $bootstrap->setEnvironment(Environment::TESTING)->configure();

        $this->kernel = $bootstrap->getApplicationKernel();
        $this->kernel->boot();

        $locator = new FileLocator([
            $this->kernel->getProjectDir() . '/src/EmptyStartPageBundle/Controller',
            $this->kernel->getProjectDir() . '/src/EmptyStartPageBundle',
            $this->kernel->getProjectDir() . '/config',
        ]);

        $attributeLoader = new AttributeRouteControllerLoader();
        $attributeDirectoryLoader = new AttributeDirectoryLoader($locator, $attributeLoader);

        $loaderResolver = new LoaderResolver(
            [
                new YamlFileLoader($locator),
                $attributeDirectoryLoader,
            ]
        );

        $this->subject = new ExtraLoader(new ApplicationStructure());
        $this->subject->setResolver($loaderResolver);
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
        Bootstrap::purgeInstance();
    }

    public function loadReturnsRouteCollection(): void
    {
        $routeCollection = $this->subject->load('@EmptyStartPageBundle/Controller/', 'extra');

        self::assertInstanceOf(RouteCollection::class, $routeCollection);
        self::assertNotNull($routeCollection->get('empty_start_page'));
    }
}
