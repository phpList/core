<?php

declare(strict_types=1);

namespace PhpList\Core\Composer;

use Composer\Package\PackageInterface;
use Composer\Script\Event;
use DomainException;
use PhpList\Core\Core\ApplicationStructure;
use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * This class provides Composer-related functionality for setting up and managing phpList modules.
 *
 * @author Oliver Klee <oliver@phplist.com>
 */
class ScriptHandler
{
    /**
     * @var string
     */
    const CORE_PACKAGE_NAME = 'phplist/core';

    /**
     * @var string
     */
    const BUNDLE_CONFIGURATION_FILE = '/config/bundles.yml';

    /**
     * @var string
     */
    const ROUTES_CONFIGURATION_FILE = '/config/routing_modules.yml';

    /**
     * @var string
     */
    const PARAMETERS_CONFIGURATION_FILE = '/config/parameters.yml';

    /**
     * @var string
     */
    const GENERAL_CONFIGURATION_FILE = '/config/config_modules.yml';

    /**
     * @var string
     */
    const PARAMETERS_TEMPLATE_FILE = '/config/parameters.yml.dist';

    /**
     * @return string absolute application root directory without the trailing slash
     *
     * @throws RuntimeException if there is no composer.json in the application root
     */
    private static function getApplicationRoot(): string
    {
        $applicationStructure = new ApplicationStructure();
        return $applicationStructure->getApplicationRoot();
    }

    /**
     * @return string absolute directory without the trailing slash
     */
    private static function getCoreDirectory(): string
    {
        return self::getApplicationRoot() . '/vendor/' . static::CORE_PACKAGE_NAME;
    }

    /**
     * Creates the "bin/" directory and its contents, copying it from the core package.
     *
     * This method must not be called for the core package itself.
     *
     * @param Event $event
     *
     * @return void
     *
     * @throws DomainException if this method is called for the core package
     */
    public static function createBinaries(Event $event): void
    {
        self::preventScriptFromCorePackage($event);
        self::mirrorDirectoryFromCore('bin');
    }

    /**
     * Creates the "public/" directory and its contents, copying it from the core package.
     *
     * This method must not be called for the core package itself.
     *
     * @param Event $event
     *
     * @return void
     *
     * @throws DomainException if this method is called for the core package
     */
    public static function createPublicWebDirectory(Event $event): void
    {
        self::preventScriptFromCorePackage($event);
        self::mirrorDirectoryFromCore('public');
    }

    /**
     * @param Event $event
     *
     * @return void
     *
     * @throws DomainException if this method is called for the core package
     */
    private static function preventScriptFromCorePackage(Event $event): void
    {
        $composer = $event->getComposer();
        $packageName = $composer->getPackage()->getName();
        if ($packageName === self::CORE_PACKAGE_NAME) {
            throw new DomainException(
                'This Composer script must not be called for the core package itself.',
                1501240572
            );
        }
    }

    /**
     * Copies a directory from the core package.
     *
     * This method overwrites existing files, but will not delete any files.
     *
     * This method must not be called for the core package itself.
     *
     * @param string $directoryWithoutSlashes directory name (without any slashes) relative to the core package
     *
     * @return void
     */
    private static function mirrorDirectoryFromCore(string $directoryWithoutSlashes): void
    {
        $directoryWithSlashes = '/' . $directoryWithoutSlashes . '/';

        $fileSystem = new Filesystem();
        $fileSystem->mirror(
            self::getCoreDirectory() . $directoryWithSlashes,
            self::getApplicationRoot() . $directoryWithSlashes,
            null,
            ['override' => true, 'delete' => false]
        );
    }

    /**
     * Echos the names and version numbers of all installed phpList modules.
     *
     * @param Event $event
     *
     * @return void
     */
    public static function listModules(Event $event): void
    {
        $packageRepository = new PackageRepository();
        $packageRepository->injectComposer($event->getComposer());

        $modules = $packageRepository->findModules();
        $maximumPackageNameLength = self::calculateMaximumPackageNameLength($modules);

        foreach ($modules as $module) {
            $paddedName = str_pad($module->getName(), $maximumPackageNameLength + 1);
            echo $paddedName . ' ' . $module->getPrettyVersion() . PHP_EOL;
        }
    }

    /**
     * @param PackageInterface[] $modules
     *
     * @return int
     */
    private static function calculateMaximumPackageNameLength(array $modules): int
    {
        $maximumLength = 0;
        foreach ($modules as $module) {
            $maximumLength = max($maximumLength, strlen($module->getName()));
        }

        return $maximumLength;
    }

    /**
     * Creates config/bundles.yml
     * (the configuration file for the Symfony bundles provided by the modules).
     *
     * @param Event $event
     *
     * @return void
     */
    public static function createBundleConfiguration(Event $event): void
    {
        self::createAndWriteFile(
            self::getApplicationRoot() . self::BUNDLE_CONFIGURATION_FILE,
            self::createAndInitializeModuleFinder($event)->createBundleConfigurationYaml()
        );
    }

    /**
     * Writes $contents to the file with the path $path.
     *
     * If the file does not exist yet, it will be created.
     *
     * If the file already exists, it will be overwritten.
     *
     * @param string $path
     * @param string $contents
     *
     * @return void
     *
     * @throws RuntimeException
     */
    private static function createAndWriteFile(string $path, string $contents): void
    {
        $fileHandle = fopen($path, 'wb');
        if ($fileHandle === false) {
            throw new RuntimeException('The file "' . $path . '" could not be opened for writing.', 1519851153);
        }

        fwrite($fileHandle, $contents);
        fclose($fileHandle);
    }

    /**
     * Creates config/routing_modules.yml
     * (the routes file for the Symfony bundles provided by the modules).
     *
     * @param Event $event
     *
     * @return void
     */
    public static function createRoutesConfiguration(Event $event): void
    {
        self::createAndWriteFile(
            self::getApplicationRoot() . self::ROUTES_CONFIGURATION_FILE,
            self::createAndInitializeModuleFinder($event)->createRouteConfigurationYaml()
        );
    }

    /**
     * @param Event $event
     *
     * @return ModuleFinder
     */
    private static function createAndInitializeModuleFinder(Event $event): ModuleFinder
    {
        $packageRepository = new PackageRepository();
        $packageRepository->injectComposer($event->getComposer());

        $bundleFinder = new ModuleFinder();
        $bundleFinder->injectPackageRepository($packageRepository);

        return $bundleFinder;
    }

    /**
     * Clears the caches of all environments. This does not warm the caches.
     *
     * @return void
     */
    public static function clearAllCaches():void
    {
        $fileSystem = new Filesystem();
        $fileSystem->remove(self::getApplicationRoot() . '/var/cache');
    }

    /**
     * Creates config/parameters.yml (the parameters configuration file).
     *
     * @return void
     */
    public static function createParametersConfiguration(): void
    {
        $configurationFilePath = self::getApplicationRoot() . self::PARAMETERS_CONFIGURATION_FILE;
        if (file_exists($configurationFilePath)) {
            return;
        }

        $templateFilePath = __DIR__ . '/../..' . static::PARAMETERS_TEMPLATE_FILE;
        $template = file_get_contents($templateFilePath);

        $secret = bin2hex(random_bytes(20));
        $configuration = sprintf($template, $secret);

        self::createAndWriteFile($configurationFilePath, $configuration);
    }

    /**
     * Creates config/config_modules.yml (the general configuration provided by the modules).
     *
     * @param Event $event
     *
     * @return void
     */
    public static function createGeneralConfiguration(Event $event): void
    {
        self::createAndWriteFile(
            self::getApplicationRoot() . self::GENERAL_CONFIGURATION_FILE,
            self::createAndInitializeModuleFinder($event)->createGeneralConfigurationYaml()
        );
    }
}
