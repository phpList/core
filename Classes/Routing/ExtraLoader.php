<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * This loader can dynamically load additional routes.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ExtraLoader extends Loader
{
    /**
     * @var bool
     */
    private $loaded = false;

    /**
     * Loads a resource.
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     *
     * @param mixed $resource the resource (unused)
     * @param string|null $type the resource type or null if unknown (unused)
     *
     * @return RouteCollection
     *
     * @throws \RuntimeException
     */
    public function load($resource, $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new \RuntimeException('Do not add the "extra" loader twice.', 1500587713150);
        }

        $routes = new RouteCollection();
        $this->addRestBundleIfAvailable($routes);

        $this->loaded = true;

        return $routes;
    }

    /**
     * Checks whether this class supports the given resource.
     *
     * @SuppressWarnings("PHPMD.UnusedFormalParameter")
     *
     * @param mixed $resource a resource (unused)
     * @param string|null $type The resource type or null if unknown
     *
     * @return bool true if this class supports the given resource, false otherwise
     */
    public function supports($resource, $type = null): bool
    {
        return $type === 'extra';
    }

    /**
     * @param RouteCollection $routes
     *
     * @return void
     */
    private function addRestBundleIfAvailable(RouteCollection $routes)
    {
        // This will later be changed so that the REST API package can register itself to the core.
        if (!$this->isRestBundleInstalled()) {
            return;
        }

        $path = '/api/v2/sessions';
        $defaults = ['_controller' => 'PhpListRestBundle:Session:create'];
        $route = new Route($path, $defaults, [], [], '', [], ['POST']);

        $routeName = 'create_session';
        $routes->add($routeName, $route);
    }

    /**
     * @return bool
     */
    private function isRestBundleInstalled(): bool
    {
        return class_exists($this->getRestBundleClassName());
    }

    /**
     * @return string
     */
    private function getRestBundleClassName(): string
    {
        return 'PhpList\\RestBundle\\PhpListRestBundle';
    }
}
