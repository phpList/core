<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Tests\Integration\Routing;

use PhpList\PhpList4\Core\ApplicationKernel;
use PhpList\PhpList4\Core\ApplicationStructure;
use PhpList\PhpList4\Core\Bootstrap;
use PhpList\PhpList4\Core\Environment;
use PhpList\PhpList4\Routing\ExtraLoader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Testcase.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ExtraLoaderTest extends TestCase
{
    /**
     * @var ExtraLoader
     */
    private $subject = null;

    /**
     * @var ApplicationKernel
     */
    private $kernel = null;

    protected function setUp()
    {
        $bootstrap = Bootstrap::getInstance();
        $bootstrap->setEnvironment(Environment::TESTING)->configure();

        $this->kernel = $bootstrap->getApplicationKernel();
        $this->kernel->boot();
        $container = $this->kernel->getContainer();

        /** @var FileLocator $fileLocator */
        $fileLocator = $container->get('file_locator');
        $yamlFileLoader = new YamlFileLoader($fileLocator);
        $loaderResolver = new LoaderResolver([$yamlFileLoader]);

        $this->subject = new ExtraLoader(new ApplicationStructure());
        $this->subject->setResolver($loaderResolver);
    }

    protected function tearDown()
    {
        $this->kernel->shutdown();
        Bootstrap::purgeInstance();
    }

    /**
     * @test
     */
    public function loadReturnsRouteCollection()
    {
        self::assertInstanceOf(RouteCollection::class, $this->subject->load('', 'extra'));
    }

    /**
     * @test
     */
    public function loadRegistersModulesRoutes()
    {
        $loadedRoutes = $this->subject->load('', 'extra');

        $route = $loadedRoutes->get('phplist/phplist4-core.application_homepage');
        self::assertNotNull($route);

        /** @var Route $route */
        self::assertSame('/', $route->getPath());
        self::assertSame(['_controller' => 'PhpListApplicationBundle:Default:index'], $route->getDefaults());
    }
}
