<?php

declare(strict_types=1);

namespace PhpList\Core\Routing;

use PhpList\Core\Core\ApplicationStructure;
use RuntimeException;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\RouteCollection;

/**
 * This loader can dynamically load additional routes.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ExtraLoader extends Loader
{
    /**
     * @var string
     */
    const MODULE_ROUTING_CONFIGURATION_FILE = '/config/routing_modules.yml';

    /**
     * @var bool
     */
    private bool $loaded = false;

    /**
     * @var ApplicationStructure
     */
    private ApplicationStructure $applicationStructure;

    /**
     * @param ApplicationStructure $applicationStructure
     */
    public function __construct(ApplicationStructure $applicationStructure)
    {
        parent::__construct();
        $this->applicationStructure = $applicationStructure;
    }

    /**
     * Loads a resource.
     *
     * @param mixed $resource the resource (unused)
     * @param string|null $type the resource type or null if unknown (unused)
     *
     * @return RouteCollection
     *
     * @throws RuntimeException
     */
    public function load($resource, string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new RuntimeException('Do not add the "extra" loader twice.', 1500587713);
        }

        $routes = new RouteCollection();
        $this->addModuleRoutes($routes);

        $this->loaded = true;

        return $routes;
    }

    /**
     * Checks whether this class supports the given resource.
     *
     * @param mixed $resource a resource (unused)
     * @param string|null $type The resource type or null if unknown
     *
     * @return bool true if this class supports the given resource, false otherwise
     */
    public function supports($resource, string $type = null): bool
    {
        return $type === 'extra';
    }

    /**
     * @param RouteCollection $routes
     *
     * @return void
     */
    private function addModuleRoutes(RouteCollection $routes): void
    {
        $bundleRoutesFilePath = $this->applicationStructure->getApplicationRoot() .
            static::MODULE_ROUTING_CONFIGURATION_FILE;

        $routesConfiguration = $this->import($bundleRoutesFilePath);
        $routes->addCollection($routesConfiguration);
    }
}
