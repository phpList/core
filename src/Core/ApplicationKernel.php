<?php

declare(strict_types=1);

namespace PhpList\Core\Core;

use Exception;
use RuntimeException;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Yaml\Yaml;

/**
 * This class takes care of processing HTTP requests using Symfony.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ApplicationKernel extends Kernel
{
    /**
     * @var ApplicationStructure
     */
    private $applicationStructure = null;

    /**
     * @return BundleInterface[]
     */
    public function registerBundles(): array
    {
        return $this->bundlesFromConfiguration();
    }

    /**
     * Returns the directory of the project/application.
     *
     * @return string absolute path without the trailing slash
     */
    public function getProjectDir(): string
    {
        return $this->getAndCreateApplicationStructure()->getCorePackageRoot();
    }

    /**
     * @return string
     */
    public function getRootDir(): string
    {
        return $this->getProjectDir();
    }

    /**
     * Returns the absolute path to the application root.
     *
     * When core is installed as a dependency (library) of an application, this method will return
     * the application's package path.
     *
     * When phpList4-core is installed stand-alone (i.e., as an application - usually only for testing),
     * this method will be the phpList4-core package path.
     *
     * @return string the absolute path without the trailing slash
     */
    private function getApplicationDir(): string
    {
        return $this->getAndCreateApplicationStructure()->getApplicationRoot();
    }

    /**
     * @return string
     */
    public function getCacheDir(): string
    {
        return $this->getApplicationDir() . '/var/cache/' . $this->getEnvironment();
    }

    /**
     * @return string
     */
    public function getLogDir(): string
    {
        return $this->getApplicationDir() . '/var/logs';
    }

    /**
     * @return ApplicationStructure
     */
    private function getAndCreateApplicationStructure(): ApplicationStructure
    {
        if ($this->applicationStructure === null) {
            $this->applicationStructure = new ApplicationStructure();
        }

        return $this->applicationStructure;
    }

    /**
     * Adds the "kernel.application_dir" parameter to the container while it is being built.
     *
     * @param ContainerBuilder $container
     *
     * @return void
     */
    protected function build(ContainerBuilder $container): void
    {
        $container->setParameter('kernel.application_dir', $this->getApplicationDir());
    }

    /**
     * Loads the container configuration.
     *
     * @param LoaderInterface $loader
     *
     * @return void
     *
     * @throws Exception
     */
    public function registerContainerConfiguration(LoaderInterface $loader): void
    {
        $loader->load($this->getApplicationDir() . '/config/parameters.yml');
        $loader->load($this->getRootDir() . '/config/config_' . $this->getEnvironment() . '.yml');
        $loader->load($this->getApplicationDir() . '/config/config_modules.yml');
    }

    /**
     * @return bool
     */
    private function shouldHaveDevelopmentBundles(): bool
    {
        return $this->environment !== Environment::PRODUCTION;
    }

    /**
     * Reads the bundles from the bundle configuration file and instantiates them.
     *
     * @return Bundle[]
     */
    private function bundlesFromConfiguration(): array
    {
        $bundles = [];

        foreach ($this->readBundleConfiguration() as $packageBundles) {
            foreach ($packageBundles as $bundleClassName) {
                if (class_exists($bundleClassName)) {
                    $bundles[] = new $bundleClassName();
                }
            }
        }

        return $bundles;
    }

    /**
     * Reads the bundle configuration file and returns two-dimensional array:
     *
     * 'package name' => [0 => 'bundle class name']
     *
     * @return string[][]
     *
     * @throws RuntimeException if the configuration file cannot be read
     */
    private function readBundleConfiguration(): array
    {
        $configurationFilePath = $this->getApplicationDir() . '/config/bundles.yml';
        if (!is_readable($configurationFilePath)) {
            throw new RuntimeException('The file "' . $configurationFilePath . '" could not be read.', 1504272377);
        }

        return Yaml::parse(file_get_contents($configurationFilePath));
    }
}
