<?php
declare(strict_types=1);

namespace PhpList\PhpList4\Composer;

use Composer\Package\PackageInterface;
use Composer\Script\Event;
use PhpList\PhpList4\Core\ApplicationStructure;
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
    const CORE_PACKAGE_NAME = 'phplist/phplist4-core';

    /**
     * @var string
     */
    const BUNDLE_CONFIGURATION_FILE = 'Configuration/bundles.yml';

    /**
     * @return string absolute application root directory without the trailing slash
     *
     * @throws \RuntimeException if there is no composer.json in the application root
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
        return self::getApplicationRoot() . '/vendor/' . self::CORE_PACKAGE_NAME;
    }

    /**
     * Creates the "bin/" directory and its contents, copying it from the phplist4-core package.
     *
     * This method must not be called for the phplist4-core package itself.
     *
     * @param Event $event
     *
     * @return void
     *
     * @throws \DomainException if this method is called for the phplist4-core package
     */
    public static function createBinaries(Event $event)
    {
        self::preventScriptFromCorePackage($event);
        self::mirrorDirectoryFromCore('bin');
    }

    /**
     * Creates the "web/" directory and its contents, copying it from the phplist4-core package.
     *
     * This method must not be called for the phplist4-core package itself.
     *
     * @param Event $event
     *
     * @return void
     *
     * @throws \DomainException if this method is called for the phplist4-core package
     */
    public static function createPublicWebDirectory(Event $event)
    {
        self::preventScriptFromCorePackage($event);
        self::mirrorDirectoryFromCore('web');
    }

    /**
     * @param Event $event
     *
     * @return void
     *
     * @throws \DomainException if this method is called for the phplist4-core package
     */
    private static function preventScriptFromCorePackage(Event $event)
    {
        $composer = $event->getComposer();
        $packageName = $composer->getPackage()->getName();
        if ($packageName === self::CORE_PACKAGE_NAME) {
            throw new \DomainException(
                'This Composer script must not be called for the phplist4-core package itself.',
                1501240572934
            );
        }
    }

    /**
     * Copies a directory from the core package.
     *
     * This method overwrites existing files, but will not delete any files.
     *
     * This method must not be called for the phplist4-core package itself.
     *
     * @param string $directoryWithoutSlashes directory name (without any slashes) relative to the core package
     *
     * @return void
     */
    private static function mirrorDirectoryFromCore(string $directoryWithoutSlashes)
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
    public static function listModules(Event $event)
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
     * Creates the configuration file for the Symfony bundles provided by the modules.
     *
     * @param Event $event
     *
     * @return void
     */
    public static function createBundleConfiguration(Event $event)
    {
        $packageRepository = new PackageRepository();
        $packageRepository->injectComposer($event->getComposer());

        $bundleFinder = new ModuleBundleFinder();
        $bundleFinder->injectPackageRepository($packageRepository);

        $configurationFilePath = __DIR__ . '/../../' . self::BUNDLE_CONFIGURATION_FILE;
        $fileHandle = fopen($configurationFilePath, 'w');
        fwrite($fileHandle, $bundleFinder->createBundleConfigurationYaml());
        fclose($fileHandle);
    }
}
