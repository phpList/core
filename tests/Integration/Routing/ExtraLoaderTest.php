<?php
declare(strict_types=1);

namespace PhpList\Core\Tests\Integration\Routing;

use Doctrine\Common\Annotations\SimpleAnnotationReader;
use PhpList\Core\Core\ApplicationKernel;
use PhpList\Core\Core\ApplicationStructure;
use PhpList\Core\Core\Bootstrap;
use PhpList\Core\Core\Environment;
use PhpList\Core\Routing\ExtraLoader;
use PHPUnit\Framework\TestCase;
use Sensio\Bundle\FrameworkExtraBundle\Routing\AnnotatedRouteControllerLoader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\HttpKernel\Config\FileLocator;
use Symfony\Component\Routing\Loader\AnnotationDirectoryLoader;
use Symfony\Component\Routing\Loader\YamlFileLoader;
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

        /** @var FileLocator $locator */
        $locator = $container->get('file_locator');
        $routeControllerLoader = new AnnotatedRouteControllerLoader(new SimpleAnnotationReader());

        $loaderResolver = new LoaderResolver(
            [
                new YamlFileLoader($locator),
                new AnnotationDirectoryLoader($locator, $routeControllerLoader),
            ]
        );

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
        static::assertInstanceOf(RouteCollection::class, $this->subject->load('', 'extra'));
    }
}
